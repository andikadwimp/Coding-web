<?php
// ═══════════════════════════════════════════════════════════════════════
// TELEGRAM WITHDRAW NOTIFICATION
// 
// Flow:
// 1. User submit WD → tgWdNotify($db,$wdId) → bot kirim message ke admin chat
//    dengan 2 tombol inline: ✅ Approve / ❌ Reject
// 2. Admin tap Approve → callback handled di tg_webhook.php → approve langsung
// 3. Admin tap Reject → bot reply ForceReply "Alasan?" → admin reply alasan
//    → webhook catat alasan + reject
// 4. Status update (dari mana pun, web admin / telegram) → edit pesan asli
//    biar tombol hilang + kasih info result
// ═══════════════════════════════════════════════════════════════════════

function tgCfg($db){
    $s=[];
    try{
        $q=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('bot_token','bot_admin_chat_id')");
        foreach($q->fetchAll() as $r)$s[$r['key']]=$r['value'];
    }catch(Exception $e){}
    return [
        'token'=>$s['bot_token']??'',
        'chat_id'=>$s['bot_admin_chat_id']??'',
    ];
}

function tgApi($token,$method,$params){
    if(!$token)return ['ok'=>false,'error'=>'no_token'];
    $ch=curl_init("https://api.telegram.org/bot$token/$method");
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($params,JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>8,
        CURLOPT_SSL_VERIFYPEER=>false,
    ]);
    $out=curl_exec($ch);curl_close($ch);
    return json_decode($out,true)?:['ok'=>false];
}

// Kolom DB: tg_msg_id untuk track message yg dikirim bot (buat edit pas approve/reject)
function tgEnsureCols($db){
    try{
        $db->exec("ALTER TABLE withdrawals ADD COLUMN tg_msg_id INT DEFAULT NULL");
    }catch(Exception $e){}
    try{
        $db->exec("ALTER TABLE withdrawals ADD COLUMN admin_note TEXT DEFAULT NULL");
    }catch(Exception $e){}
    // Table untuk tracking state reject (admin lagi di-prompt alasan)
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS tg_wd_state(
            tg_chat_id VARCHAR(50),
            awaiting_reject_id INT UNSIGNED,
            prompt_msg_id INT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(tg_chat_id)
        ) ENGINE=InnoDB");
    }catch(Exception $e){}
}

// Kirim notif WD baru ke admin TG dengan 2 tombol
function tgWdNotify($db,$wdId){
    $cfg=tgCfg($db);
    if(!$cfg['token']||!$cfg['chat_id'])return false;
    tgEnsureCols($db);

    // Load WD detail
    $q=$db->prepare("SELECT w.*,u.phone,u.username FROM withdrawals w LEFT JOIN users u ON u.id=w.user_id WHERE w.id=?");
    $q->execute([$wdId]);
    $w=$q->fetch();
    if(!$w)return false;

    $amtFmt=number_format($w['amount'],0,',','.');
    $text="💰 <b>PENARIKAN BARU</b>\n\n"
         ."🆔 ID: <code>#{$w['id']}</code>\n"
         ."👤 User: <b>".htmlspecialchars($w['username']??'-')."</b>\n"
         ."📱 HP: <code>".htmlspecialchars($w['phone']??'-')."</code>\n"
         ."💵 Jumlah: <b>Rp $amtFmt</b>\n\n"
         ."🏦 Bank: <b>".htmlspecialchars($w['bank_name'])."</b>\n"
         ."📝 Nama: <b>".htmlspecialchars($w['acc_name'])."</b>\n"
         ."🔢 Rek: <code>".htmlspecialchars($w['acc_number'])."</code>\n\n"
         ."⏰ ".date('d/m/Y H:i');

    $kb=[
        'inline_keyboard'=>[[
            ['text'=>'✅ Approve','callback_data'=>"wd_approve_{$w['id']}"],
            ['text'=>'❌ Reject','callback_data'=>"wd_reject_{$w['id']}"],
        ]]
    ];

    $res=tgApi($cfg['token'],'sendMessage',[
        'chat_id'=>$cfg['chat_id'],
        'text'=>$text,
        'parse_mode'=>'HTML',
        'reply_markup'=>$kb,
    ]);

    if(!empty($res['ok'])&&!empty($res['result']['message_id'])){
        // Simpan msg_id buat edit nanti
        try{
            $db->prepare("UPDATE withdrawals SET tg_msg_id=? WHERE id=?")
               ->execute([intval($res['result']['message_id']),$wdId]);
        }catch(Exception $e){}
        return true;
    }
    return false;
}

// Edit pesan asli saat status berubah (dari web atau TG) → hapus tombol + tambahin result
function tgWdMarkResolved($db,$wdId,$status,$note='',$byTg=false){
    $cfg=tgCfg($db);
    if(!$cfg['token']||!$cfg['chat_id'])return;

    $q=$db->prepare("SELECT w.*,u.phone,u.username FROM withdrawals w LEFT JOIN users u ON u.id=w.user_id WHERE w.id=?");
    $q->execute([$wdId]);
    $w=$q->fetch();
    if(!$w||empty($w['tg_msg_id']))return;

    $amtFmt=number_format($w['amount'],0,',','.');
    $statusEmoji=$status==='approved'?'✅':'❌';
    $statusText=$status==='approved'?'DISETUJUI':'DITOLAK';
    $resolvedBy=$byTg?'via Telegram':'via Web';

    $text="💰 <b>PENARIKAN #{$w['id']}</b> — {$statusEmoji} <b>$statusText</b>\n\n"
         ."👤 ".htmlspecialchars($w['username']??'-')." (".htmlspecialchars($w['phone']??'-').")\n"
         ."💵 Rp $amtFmt\n"
         ."🏦 ".htmlspecialchars($w['bank_name'])." — ".htmlspecialchars($w['acc_name'])." (".htmlspecialchars($w['acc_number']).")\n\n";

    if($status==='rejected'&&$note){
        $text.="📝 <b>Alasan:</b> ".htmlspecialchars($note)."\n\n";
    }
    $text.="🕒 Diproses: ".date('d/m/Y H:i')." ($resolvedBy)";

    tgApi($cfg['token'],'editMessageText',[
        'chat_id'=>$cfg['chat_id'],
        'message_id'=>intval($w['tg_msg_id']),
        'text'=>$text,
        'parse_mode'=>'HTML',
        // Tanpa reply_markup → tombol hilang
    ]);
}
