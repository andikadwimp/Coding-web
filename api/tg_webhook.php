<?php
// ═══════════════════════════════════════════════════════════════════════
// TELEGRAM WEBHOOK
// Admin reply di Telegram → otomatis masuk ke chat user di web.
// Juga handle callback button WD approve/reject.
//
// Setup sekali (via browser atau cURL):
//   https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<DOMAIN>/api/tg_webhook.php
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__.'/../includes/config.php';
header('Content-Type: application/json; charset=UTF-8');

$raw=file_get_contents('php://input');
@file_put_contents(__DIR__.'/../tg_log.txt',date('Y-m-d H:i:s')." IN: ".$raw."\n",FILE_APPEND);

$upd=json_decode($raw,true);
if(!$upd){echo'{"ok":false}';exit;}

// ═══ LOAD CONFIG — support dua set token: cs_tg (CS chat) dan bot (general) ═══
$cfg=[];
try{$q=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('cs_tg_token','cs_tg_chat_id','bot_token','bot_admin_chat_id')");
  foreach($q->fetchAll() as $r)$cfg[$r['key']]=$r['value'];
}catch(Exception $e){}

$csTgToken=$cfg['cs_tg_token']??'';
$csTgChatId=$cfg['cs_tg_chat_id']??'';
$botToken=$cfg['bot_token']??'';
$botChatId=$cfg['bot_admin_chat_id']??'';

// ═══════════════════════════════════════════════════════════════════════
// HANDLER 1: CALLBACK QUERY (tap tombol Approve/Reject WD)
// ═══════════════════════════════════════════════════════════════════════
$cbq=$upd['callback_query']??null;
if($cbq){
    $cbData=$cbq['data']??'';
    $cbChatId=(string)($cbq['message']['chat']['id']??'');
    $cbMsgId=intval($cbq['message']['message_id']??0);
    $cbQueryId=$cbq['id']??'';

    // Validate: hanya admin chat (bot_admin_chat_id) yang boleh operate
    if($botChatId && $cbChatId===(string)$botChatId && $botToken){
        require_once __DIR__.'/../includes/tg_wd.php';
        tgEnsureCols($db);

        // Parse callback: wd_approve_123 atau wd_reject_123
        if(preg_match('/^wd_(approve|reject)_(\d+)$/',$cbData,$m)){
            $act=$m[1];
            $wdId=intval($m[2]);

            // Load WD
            $wq=$db->prepare("SELECT * FROM withdrawals WHERE id=? LIMIT 1");
            $wq->execute([$wdId]);
            $wd=$wq->fetch();

            // Answer callback dulu biar loading icon di TG hilang
            tgApi($botToken,'answerCallbackQuery',['callback_query_id'=>$cbQueryId]);

            if(!$wd){
                tgApi($botToken,'sendMessage',[
                    'chat_id'=>$cbChatId,
                    'text'=>"⚠️ WD #$wdId tidak ditemukan.",
                ]);
                echo'{"ok":true,"msg":"wd_not_found"}';exit;
            }

            if($wd['status']!=='pending'){
                tgApi($botToken,'sendMessage',[
                    'chat_id'=>$cbChatId,
                    'text'=>"ℹ️ WD #$wdId sudah diproses sebelumnya (status: {$wd['status']}).",
                ]);
                // Refresh pesan biar tombol hilang
                tgWdMarkResolved($db,$wdId,$wd['status'],$wd['admin_note']??'',true);
                echo'{"ok":true,"msg":"already_processed"}';exit;
            }

            if($act==='approve'){
                // Langsung approve — tanpa note
                $db->prepare("UPDATE withdrawals SET status='approved',processed_at=NOW(),admin_note=? WHERE id=?")
                   ->execute(['Disetujui via Telegram',$wdId]);

                // Memo + push notif ke user
                if(function_exists('autoMemo')){
                    autoMemo($db,$wd['user_id'],'Penarikan Disetujui','Penarikan Rp '.number_format($wd['amount'],0,',','.').' telah diproses.');
                }
                try{
                    require_once __DIR__.'/../includes/webpush.php';
                    pushNotify($db,$wd['user_id'],'✅ Penarikan Disetujui','Penarikan Rp '.number_format($wd['amount'],0,',','.').' sedang diproses ke rekening kamu.','/withdraw.php');
                }catch(Exception $e){}

                // Edit pesan asli → hapus tombol + tambah hasil
                tgWdMarkResolved($db,$wdId,'approved','',true);

                echo'{"ok":true,"msg":"approved"}';exit;
            }else{
                // Reject → set state "awaiting reject reason" untuk chat ini
                // Lalu kirim ForceReply prompt
                $promptRes=tgApi($botToken,'sendMessage',[
                    'chat_id'=>$cbChatId,
                    'text'=>"📝 <b>Tolak WD #$wdId</b>\n\nReply pesan ini dengan <b>alasan penolakan</b>:",
                    'parse_mode'=>'HTML',
                    'reply_markup'=>[
                        'force_reply'=>true,
                        'input_field_placeholder'=>'Contoh: Nama rekening tidak sesuai',
                    ],
                ]);
                $promptMsgId=intval($promptRes['result']['message_id']??0);

                // Simpan state: chat ini sedang awaiting reject reason untuk WD ini
                $db->prepare("REPLACE INTO tg_wd_state(tg_chat_id,awaiting_reject_id,prompt_msg_id,updated_at) VALUES(?,?,?,NOW())")
                   ->execute([$cbChatId,$wdId,$promptMsgId]);

                echo'{"ok":true,"msg":"reject_prompt_sent"}';exit;
            }
        }
    }
    // Callback lain — ignore
    tgApi($botToken,'answerCallbackQuery',['callback_query_id'=>$cbQueryId]);
    echo'{"ok":true,"msg":"callback_ignored"}';exit;
}

// ═══════════════════════════════════════════════════════════════════════
// HANDLER 2: MESSAGE (bisa reply untuk CS chat, atau reply alasan reject WD)
// ═══════════════════════════════════════════════════════════════════════
$msg=$upd['message']??$upd['edited_message']??null;
if(!$msg){echo'{"ok":true,"msg":"no_message"}';exit;}

$fromChat=(string)($msg['chat']['id']??'');
$msgText=trim((string)($msg['text']??$msg['caption']??''));

// ─── CHECK: admin sedang reply alasan reject WD? ───
if($botChatId && $fromChat===(string)$botChatId && $botToken && $msg['reply_to_message']??null){
    $replyToMsgId=intval($msg['reply_to_message']['message_id']??0);
    // Cari state pending
    $st=$db->prepare("SELECT awaiting_reject_id,prompt_msg_id FROM tg_wd_state WHERE tg_chat_id=? AND prompt_msg_id=? LIMIT 1");
    $st->execute([$fromChat,$replyToMsgId]);
    $state=$st->fetch();

    if($state && !empty($state['awaiting_reject_id'])){
        $wdId=intval($state['awaiting_reject_id']);
        $reason=$msgText!==''?$msgText:'Ditolak admin';

        require_once __DIR__.'/../includes/tg_wd.php';
        tgEnsureCols($db);

        // Load WD
        $wq=$db->prepare("SELECT * FROM withdrawals WHERE id=? AND status='pending' LIMIT 1");
        $wq->execute([$wdId]);
        $wd=$wq->fetch();

        if(!$wd){
            tgApi($botToken,'sendMessage',[
                'chat_id'=>$fromChat,
                'reply_to_message_id'=>$msg['message_id'],
                'text'=>"⚠️ WD #$wdId tidak pending lagi. Batal.",
            ]);
            // Clear state
            $db->prepare("DELETE FROM tg_wd_state WHERE tg_chat_id=?")->execute([$fromChat]);
            echo'{"ok":true,"msg":"wd_not_pending"}';exit;
        }

        // Refund + update status
        if(function_exists('logTx')){
            logTx($db,$wd['user_id'],'refund',$wd['amount'],'Penarikan ditolak: '.$reason);
        }
        $db->prepare("UPDATE withdrawals SET status='rejected',processed_at=NOW(),admin_note=? WHERE id=?")
           ->execute([$reason,$wdId]);

        // Memo + push notif
        if(function_exists('autoMemo')){
            autoMemo($db,$wd['user_id'],'Penarikan Ditolak','Penarikan Rp '.number_format($wd['amount'],0,',','.').' ditolak. '.$reason);
        }
        try{
            require_once __DIR__.'/../includes/webpush.php';
            pushNotify($db,$wd['user_id'],'❌ Penarikan Ditolak','Penarikan Rp '.number_format($wd['amount'],0,',','.').' ditolak. '.$reason,'/withdraw.php');
        }catch(Exception $e){}

        // Edit pesan asli → hasil
        tgWdMarkResolved($db,$wdId,'rejected',$reason,true);

        // Confirm ke admin
        tgApi($botToken,'sendMessage',[
            'chat_id'=>$fromChat,
            'reply_to_message_id'=>$msg['message_id'],
            'text'=>"✅ WD #$wdId ditolak. Saldo user di-refund.",
        ]);

        // Clear state
        $db->prepare("DELETE FROM tg_wd_state WHERE tg_chat_id=?")->execute([$fromChat]);

        echo'{"ok":true,"msg":"wd_rejected","wd_id":'.$wdId.'}';exit;
    }
}

// ═══════════════════════════════════════════════════════════════════════
// HANDLER 3: CS CHAT REPLY (existing logic)
// ═══════════════════════════════════════════════════════════════════════
$token=$csTgToken;
$allowedChatId=$csTgChatId;

// Sanitasi: chat harus sama dengan admin_chat_id yang di-set (untuk CS)
if($allowedChatId&&$fromChat!==(string)$allowedChatId){
  @file_put_contents(__DIR__.'/../tg_log.txt',date('Y-m-d H:i:s')." REJECT chat=$fromChat (expected $allowedChatId)\n",FILE_APPEND);
  echo'{"ok":true,"msg":"unauthorized_chat"}';exit;
}

// Harus reply message (admin reply ke message bot kita)
$reply=$msg['reply_to_message']??null;
if(!$reply){
  // Admin ngirim text biasa (tanpa reply) → ignore
  echo'{"ok":true,"msg":"not_a_reply"}';exit;
}

$replyToMsgId=intval($reply['message_id']??0);
if(!$replyToMsgId){echo'{"ok":true,"msg":"no_reply_msg_id"}';exit;}

// Cari user berdasarkan reply_to_message_id
// Mapping: cs_user_tg_map.last_tg_msg_id = message_id yg dikirim bot saat forward user text
$find=$db->prepare("SELECT user_id FROM cs_user_tg_map WHERE last_tg_msg_id=? LIMIT 1");
$find->execute([$replyToMsgId]);
$uid=intval($find->fetchColumn());
if(!$uid){
  // Fallback: coba cari via cs_messages
  $f2=$db->prepare("SELECT user_id FROM cs_messages WHERE tg_message_id=? LIMIT 1");
  $f2->execute([$replyToMsgId]);
  $uid=intval($f2->fetchColumn());
}
if(!$uid){
  @file_put_contents(__DIR__.'/../tg_log.txt',date('Y-m-d H:i:s')." no_user_for_reply_id=$replyToMsgId\n",FILE_APPEND);
  // Kasih tau admin di Telegram
  if($token){
    $ch=curl_init("https://api.telegram.org/bot$token/sendMessage");
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query([
      'chat_id'=>$fromChat,
      'reply_to_message_id'=>$msg['message_id'],
      'text'=>'⚠️ Gagal mengirim: user tidak ditemukan. Pesan lama mungkin sudah expired.',
    ]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6]);
    curl_exec($ch);curl_close($ch);
  }
  echo'{"ok":true,"msg":"user_not_found"}';exit;
}

// Ambil text reply dari admin
$replyText=$msgText;
if($replyText===''){echo'{"ok":true,"msg":"empty_reply"}';exit;}

// Simpan sebagai admin message ke user
$db->prepare("INSERT INTO cs_messages(user_id,sender,message,tg_message_id) VALUES(?,?,?,?)")
   ->execute([$uid,'admin',$replyText,intval($msg['message_id'])]);

// Confirm ke admin di Telegram (✓ kecil di samping message)
if($token){
  $ch=curl_init("https://api.telegram.org/bot$token/sendMessage");
  curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query([
    'chat_id'=>$fromChat,
    'reply_to_message_id'=>$msg['message_id'],
    'text'=>"✅ Terkirim ke user #$uid",
  ]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6]);
  curl_exec($ch);curl_close($ch);
}

echo'{"ok":true,"msg":"delivered","uid":'.$uid.'}';
