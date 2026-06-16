<?php
// ═══════════════════════════════════════════════════════════════════════
// CS CHAT API v2 — Clean version
// - Button-based menu (1 level flat)
// - Manual text → Telegram bot
// - Admin reply di Telegram → auto masuk ke web user
// ═══════════════════════════════════════════════════════════════════════

require_once __DIR__.'/../includes/config.php';
header('Content-Type: application/json; charset=UTF-8');

// ─── Schema ───
try{$db->exec("CREATE TABLE IF NOT EXISTS cs_buttons(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 label VARCHAR(100) NOT NULL,
 reply_text TEXT,
 reply_image VARCHAR(500) DEFAULT NULL,
 sort_order INT DEFAULT 0,
 is_active TINYINT DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_sort (sort_order, id)
) ENGINE=InnoDB");}catch(Exception $e){}

try{$db->exec("CREATE TABLE IF NOT EXISTS cs_messages(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 sender ENUM('user','admin','bot') NOT NULL,
 message TEXT,
 image_url VARCHAR(500) DEFAULT NULL,
 tg_message_id BIGINT DEFAULT NULL,
 is_read TINYINT DEFAULT 0,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_user (user_id, id),
 INDEX idx_unread (user_id, is_read)
) ENGINE=InnoDB");}catch(Exception $e){}

try{$db->exec("CREATE TABLE IF NOT EXISTS cs_user_tg_map(
 user_id INT UNSIGNED PRIMARY KEY,
 last_tg_msg_id BIGINT DEFAULT NULL,
 updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");}catch(Exception $e){}

// Seed default buttons kalau kosong
try{
  $cnt=intval($db->query("SELECT COUNT(*) FROM cs_buttons")->fetchColumn());
  if($cnt===0){
    $seed=[
      ['Deposit',"Untuk deposit:\n\n1. Pilih menu Deposit di halaman utama\n2. Masukkan nominal\n3. Pilih metode pembayaran (QRIS/DANA/GoPay/BCA)\n4. Bayar sesuai nominal yg tertera\n5. Saldo otomatis masuk maksimal 5 menit",''],
      ['Withdraw',"Syarat withdraw:\n\n1. Turnover sudah mencapai target\n2. Sudah setting rekening bank di menu Profil\n3. Minimal tarik Rp 50.000\n4. Proses max 15 menit di jam kerja",''],
      ['Lupa Password',"Silakan hubungi CS via chat ini, tulis:\n\n- Nomor HP akun\n- Nominal deposit terakhir\n- Tanggal deposit terakhir",''],
      ['Bonus & Promo',"Info bonus & promo ada di menu Promosi. Klaim langsung di halaman Bonus & Promo.",''],
      ['Lainnya',"Silakan ketik pertanyaan langsung di chat, CS akan membalas.",''],
    ];
    $st=$db->prepare("INSERT INTO cs_buttons(label,reply_text,reply_image,sort_order) VALUES(?,?,?,?)");
    foreach($seed as $i=>$r)$st->execute([$r[0],$r[1],$r[2],$i]);
  }
}catch(Exception $e){}

// ─── Helpers ───
function tgSettings($db){
  $s=[];
  try{$q=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('cs_tg_token','cs_tg_chat_id','cs_greeting')");
    foreach($q->fetchAll() as $r)$s[$r['key']]=$r['value'];
  }catch(Exception $e){}
  return $s;
}

function tgSend($token,$chatId,$text){
  if(!$token||!$chatId)return null;
  $ch=curl_init("https://api.telegram.org/bot$token/sendMessage");
  curl_setopt_array($ch,[
    CURLOPT_POST=>true,
    CURLOPT_POSTFIELDS=>http_build_query([
      'chat_id'=>$chatId,
      'text'=>$text,
      'parse_mode'=>'HTML',
    ]),
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>8,
    CURLOPT_SSL_VERIFYPEER=>false,
  ]);
  $res=curl_exec($ch);curl_close($ch);
  $d=json_decode($res,true);
  return $d;
}

function err($msg){echo json_encode(['ok'=>false,'error'=>$msg]);exit;}
function ok($data=[]){echo json_encode(['ok'=>true]+$data);exit;}
function authUid(){global $db;
  $tok=$_COOKIE['lx_token']??'';
  if(!$tok)return 0;
  try{$s=$db->prepare("SELECT id FROM users WHERE auth_token=? LIMIT 1");$s->execute([$tok]);
    return intval($s->fetchColumn());
  }catch(Exception $e){return 0;}
}

// ─── Routing ───
$raw=file_get_contents('php://input');
$d=[];
if($raw&&($j=json_decode($raw,true))&&is_array($j))$d=$j;
$d=array_merge($_GET,$_POST,$d);
$action=$d['action']??'';

// ─── USER ENDPOINTS ───

// Get menu buttons
if($action==='menu'){
  $rows=$db->query("SELECT id,label,reply_text,reply_image FROM cs_buttons WHERE is_active=1 ORDER BY sort_order ASC, id ASC")->fetchAll();
  $greet=$db->prepare("SELECT `value` FROM settings WHERE `key`='cs_greeting' LIMIT 1");
  $greet->execute();$greeting=trim((string)$greet->fetchColumn())?:'Ada kendala apa ya bosku?';
  ok(['greeting'=>$greeting,'buttons'=>$rows]);
}

// Klik button — return reply (text+image) & log ke DB
if($action==='click_button'){
  $uid=authUid();if(!$uid)err('NOT_LOGGED_IN',401);
  $bid=intval($d['button_id']??0);
  $b=$db->prepare("SELECT * FROM cs_buttons WHERE id=? AND is_active=1");
  $b->execute([$bid]);$btn=$b->fetch();
  if(!$btn)err('Button tidak ditemukan');
  // Log: user klik & bot reply
  $db->prepare("INSERT INTO cs_messages(user_id,sender,message) VALUES(?,?,?)")->execute([$uid,'user','[KLIK] '.$btn['label']]);
  $db->prepare("INSERT INTO cs_messages(user_id,sender,message,image_url) VALUES(?,?,?,?)")->execute([$uid,'bot',$btn['reply_text'],$btn['reply_image']]);
  ok(['reply'=>$btn['reply_text'],'image'=>$btn['reply_image'],'label'=>$btn['label']]);
}

// User kirim text manual → Telegram bot
if($action==='send_text'){
  $uid=authUid();if(!$uid)err('NOT_LOGGED_IN',401);
  $txt=trim((string)($d['message']??''));
  if($txt==='')err('Pesan kosong');
  if(mb_strlen($txt)>2000)err('Pesan terlalu panjang');

  // Ambil user info
  $uq=$db->prepare("SELECT username,phone,total_deposit FROM users WHERE id=?");
  $uq->execute([$uid]);$u=$uq->fetch();
  $s=tgSettings($db);
  $token=$s['cs_tg_token']??'';$chatId=$s['cs_tg_chat_id']??'';

  // Log ke DB
  $db->prepare("INSERT INTO cs_messages(user_id,sender,message) VALUES(?,?,?)")->execute([$uid,'user',$txt]);

  // Kirim ke Telegram
  $tgMsg="💬 <b>Chat dari user</b>\n".
         "━━━━━━━━━━━━━━━\n".
         "👤 User: <code>{$u['username']}</code>\n".
         "📱 HP: <code>{$u['phone']}</code>\n".
         "💰 Total Depo: Rp ".number_format(intval($u['total_deposit']),0,',','.')."\n".
         "🆔 ID: <code>$uid</code>\n".
         "━━━━━━━━━━━━━━━\n\n".
         "<b>Pesan:</b>\n".htmlspecialchars($txt)."\n\n".
         "<i>Reply message ini untuk membalas user.</i>";

  $res=tgSend($token,$chatId,$tgMsg);
  if($res&&isset($res['result']['message_id'])){
    $tgMsgId=$res['result']['message_id'];
    // Mapping: tg_msg_id → user_id (biar pas admin reply, kita tau reply utk user mana)
    $db->prepare("REPLACE INTO cs_user_tg_map(user_id,last_tg_msg_id) VALUES(?,?)")->execute([$uid,$tgMsgId]);
    // Update msg terakhir dengan tg_msg_id
    $db->prepare("UPDATE cs_messages SET tg_message_id=? WHERE user_id=? AND sender='user' ORDER BY id DESC LIMIT 1")->execute([$tgMsgId,$uid]);
  }

  ok(['sent'=>true,'tg_ok'=>isset($res['result'])]);
}

// Polling: ambil message baru (sejak last_id)
if($action==='poll'){
  $uid=authUid();if(!$uid)err('NOT_LOGGED_IN',401);
  $lastId=intval($d['last_id']??0);
  $s=$db->prepare("SELECT id,sender,message,image_url,created_at FROM cs_messages WHERE user_id=? AND id>? ORDER BY id ASC LIMIT 50");
  $s->execute([$uid,$lastId]);
  $rows=$s->fetchAll();
  // Mark admin messages as read
  if(!empty($rows)){
    $db->prepare("UPDATE cs_messages SET is_read=1 WHERE user_id=? AND sender IN('admin','bot') AND id<=?")
       ->execute([$uid,max(array_column($rows,'id'))]);
  }
  ok(['messages'=>$rows]);
}

// Ambil full history user
if($action==='history'){
  $uid=authUid();if(!$uid)err('NOT_LOGGED_IN',401);
  $s=$db->prepare("SELECT id,sender,message,image_url,created_at FROM cs_messages WHERE user_id=? ORDER BY id DESC LIMIT 50");
  $s->execute([$uid]);
  $rows=array_reverse($s->fetchAll());
  ok(['messages'=>$rows]);
}

// Count unread for badge
if($action==='unread'){
  $uid=authUid();if(!$uid)ok(['count'=>0]);
  $s=$db->prepare("SELECT COUNT(*) FROM cs_messages WHERE user_id=? AND sender IN('admin','bot') AND is_read=0");
  $s->execute([$uid]);
  ok(['count'=>intval($s->fetchColumn())]);
}

err('Action tidak dikenali');
