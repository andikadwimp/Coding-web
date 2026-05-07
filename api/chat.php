<?php
require_once __DIR__.'/../includes/config.php';

// Auto-create tables
try{$db->exec("CREATE TABLE IF NOT EXISTS chat_sessions(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED DEFAULT NULL,
 guest_key VARCHAR(64) DEFAULT NULL,
 username VARCHAR(100) DEFAULT'Tamu',
 tg_msg_id BIGINT DEFAULT NULL,
 status ENUM('open','closed') DEFAULT'open',
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 last_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB");}catch(Exception $e){}

try{$db->exec("CREATE TABLE IF NOT EXISTS chat_messages(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 session_id INT UNSIGNED NOT NULL,
 sender ENUM('user','admin') NOT NULL,
 msg_type ENUM('text','image') DEFAULT'text',
 content TEXT,
 file_url VARCHAR(500) DEFAULT NULL,
 tg_msg_id BIGINT DEFAULT NULL,
 is_read TINYINT DEFAULT 0,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");}catch(Exception $e){}

try{$db->exec("CREATE TABLE IF NOT EXISTS chat_autoreplies(
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 question TEXT,
 answer TEXT,
 hit_count INT DEFAULT 1,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");}catch(Exception $e){}

// Track berapa kali user bahas topik yg sama dalam 1 sesi (anti-spam, escalation reply)
try{$db->exec("CREATE TABLE IF NOT EXISTS chat_topic_count(
 session_id INT UNSIGNED NOT NULL,
 topic VARCHAR(30) NOT NULL,
 cnt INT DEFAULT 1,
 last_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (session_id,topic)
) ENGINE=InnoDB");}catch(Exception $e){}

// Mapping Telegram message_id → session_id (setiap pesan yang dikirim bot ke admin)
// Memungkinkan admin reply ke pesan MANA SAJA di thread dan tetap ter-route dengan benar.
try{$db->exec("CREATE TABLE IF NOT EXISTS chat_tg_map(
 tg_msg_id BIGINT PRIMARY KEY,
 session_id INT UNSIGNED NOT NULL,
 created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_sess(session_id)
) ENGINE=InnoDB");}catch(Exception $e){}

$action=$_GET['action']??($_POST['action']??'');
if(!$action)$action=json_decode(file_get_contents('php://input'),true)['action']??'';
// Global request data — dipakai banyak action
$d=json_decode(file_get_contents('php://input'),true)??$_POST??[];

// Helper: get bot settings
function getBotSettings($db){
 $rows=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('bot_token','bot_admin_chat_id','bot_welcome','bot_smart_reply')")->fetchAll();
 $s=[];foreach($rows as $r)$s[$r['key']]=$r['value'];
 return $s;
}

// Helper: send to Telegram (supports inline keyboard)
function tgSend($token,$chatId,$text,$replyTo=null,$keyboard=null){
 if(!$token||!$chatId)return null;
 $data=['chat_id'=>$chatId,'text'=>$text,'parse_mode'=>'HTML','disable_web_page_preview'=>true];
 if($replyTo)$data['reply_to_message_id']=$replyTo;
 if($keyboard)$data['reply_markup']=json_encode(['inline_keyboard'=>$keyboard]);
 $ch=curl_init("https://api.telegram.org/bot$token/sendMessage");
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($data),
 CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
 CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
 $res=json_decode(curl_exec($ch),true);curl_close($ch);
 return $res['result']['message_id']??null;
}

// Helper: fetch user context (phone, vip, balance, turnover) for richer notifications
function getUserContext($db,$uid){
 if(!$uid)return null;
 try{
 $q=$db->prepare("SELECT id,username,display_id,phone,vip_level,balance,total_deposit,total_turnover,created_at FROM users WHERE id=?");
 $q->execute([$uid]);
 return $q->fetch()?:null;
 }catch(Exception $e){return null;}
}

// Helper: get/create session — WAJIB login, ga support guest lagi
function getSession($db,$uid,$guestKey,&$settings){
 if(!$uid)return null;
 $s=$db->prepare("SELECT * FROM chat_sessions WHERE user_id=? AND status='open'ORDER BY id DESC LIMIT 1");
 $s->execute([$uid]);$sess=$s->fetch();
 if(!$sess){
 $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$uid]);$uname=$u->fetchColumn()?:'User';
 $db->prepare("INSERT INTO chat_sessions(user_id,username) VALUES(?,?)")->execute([$uid,$uname]);
 $sid=$db->lastInsertId();
 $q=$db->prepare("SELECT * FROM chat_sessions WHERE id=?");$q->execute([$sid]);$sess=$q->fetch();
 }
 return $sess;
}

// Parse special syntax: #a text|link → <a href="link">cek disini</a>
function parseMsg($text){
 // #a Label|URL or #a URL
 $text=preg_replace_callback('/#a ([^|]+)\|(\S+)/',function($m){
 return'<a href="'.htmlspecialchars($m[2]).'"target="_blank"style="color:#4ade80;text-decoration:underline">'.htmlspecialchars($m[1]).'</a>';
 },$text);
 $text=preg_replace_callback('/#a (https?:\/\/\S+)/',function($m){
 return'<a href="'.htmlspecialchars($m[1]).'"target="_blank"style="color:#4ade80;text-decoration:underline">cek disini</a>';
 },$text);
 return $text;
}

// ─── DETEKSI TOPIK PESAN USER (keyword-based) ───
// Return:'depo_gagal'|'depo_cara'|'wd_gagal'|'wd_cara'|'game_error'|'login'|'bonus'|'rekening'| null
function detectTopic($text){
 $t=''.strtolower(trim($text)).'';
 // Hapus tanda baca biar matching lebih flexible
 $t=preg_replace('/[^\p{L}\p{N}\s]/u','',$t);
 $t=preg_replace('/\s+/','',$t);

 // === DEPOSIT GAGAL/BERMASALAH ===
 $depoGagal=['depo.*belum.*masuk','depo.*ga.*masuk','depo.*tidak.*masuk','deposit.*belum.*masuk','deposit.*ga.*masuk','deposit.*tidak.*masuk','saldo.*belum.*masuk','saldo.*ga.*masuk','saldo.*tidak.*masuk','udah.*depo.*belum','udh.*depo.*belum','udah.*bayar.*belum','udh.*bayar.*belum','sudah.*bayar.*belum','sudah.*transfer.*belum','depo.*pending','deposit.*pending','depo.*kagak.*masuk','depo.*nggak.*masuk','transfer.*belum.*masuk','transfer.*tidak.*masuk','dana.*belum.*masuk','duit.*belum.*masuk','depo.*gagal','deposit.*gagal','sdh.*tf.*belum','sdh.*tf.*kagak','tf.*kagak.*masuk','tf.*ga.*masuk'];
 foreach($depoGagal as $p)if(preg_match('/'.$p.'/u',$t))return'depo_gagal';

 // === WITHDRAW GAGAL ===
 $wdGagal=['wd.*belum','wd.*ga.*masuk','wd.*tidak.*masuk','wd.*gagal','wd.*pending','wd.*lama','withdraw.*belum','withdraw.*pending','withdraw.*lama','tarik.*belum.*masuk','tarik.*ga.*masuk','penarikan.*belum','penarikan.*pending','wd.*ditolak','wd.*kapan'];
 foreach($wdGagal as $p)if(preg_match('/'.$p.'/u',$t))return'wd_gagal';

 // === GAME BERMASALAH/ERROR ===
 $gameErr=['game.*error','game.*tidak.*bisa','game.*ga.*bisa','game.*kagak.*bisa','game.*bermasalah','game.*lemot','game.*lag','game.*nge.*lag','game.*loading','game.*stuck','game.*hang','game.*macet','provider.*error','provider.*ga.*bisa','provider.*tidak.*bisa','slot.*error','slot.*ga.*bisa','slot.*kagak','game.*ga.*kebuka','game.*tidak.*terbuka','tidak.*bisa.*main','ga.*bisa.*main','kagak.*bisa.*main'];
 foreach($gameErr as $p)if(preg_match('/'.$p.'/u',$t))return'game_error';

 // === CARA DEPOSIT ===
 $depoCara=['cara.*depo','cara.*deposit','gimana.*depo','bagaimana.*depo','gmn.*depo','minim.*depo','minimal.*depo','nominal.*depo','metode.*depo','metode.*pembayaran','bisa.*depo.*pake','depo.*pake.*apa','depo.*via','tutorial.*depo'];
 foreach($depoCara as $p)if(preg_match('/'.$p.'/u',$t))return'depo_cara';

 // === CARA WITHDRAW ===
 $wdCara=['cara.*wd','cara.*tarik','cara.*withdraw','gimana.*wd','gimana.*tarik','minim.*wd','minimal.*wd','minimal.*tarik','syarat.*wd','syarat.*tarik','syarat.*penarikan','turnover.*berapa','turnover.*x.*berapa','to.*berapa','wd.*berapa.*lama','tarik.*berapa.*lama'];
 foreach($wdCara as $p)if(preg_match('/'.$p.'/u',$t))return'wd_cara';

 // === LOGIN/REGISTER ===
 $loginH=['lupa.*sandi','lupa.*password','lupa.*pw','reset.*sandi','reset.*password','ga.*bisa.*login','tidak.*bisa.*login','kagak.*bisa.*login','akun.*ke.*lock','akun.*terkunci','akun.*ke.*ban','daftar.*gimana','cara.*daftar','cara.*register'];
 foreach($loginH as $p)if(preg_match('/'.$p.'/u',$t))return'login';

 // === BONUS ===
 $bonusH=['bonus.*belum','bonus.*tidak.*masuk','bonus.*ga.*masuk','bonus.*kagak.*masuk','klaim.*bonus','cara.*bonus','syarat.*bonus','rebate.*belum','rebate.*kapan','referral.*belum','undangan.*belum','hadiah.*belum'];
 foreach($bonusH as $p)if(preg_match('/'.$p.'/u',$t))return'bonus';

 // === REKENING / DATA AKUN ===
 $rekH=['ganti.*rekening','ubah.*rekening','tambah.*rekening','rekening.*salah','no.*rek.*salah','nomor.*rekening.*salah','ubah.*nama','ganti.*nama','data.*salah'];
 foreach($rekH as $p)if(preg_match('/'.$p.'/u',$t))return'rekening';

 // === AKUN DUPLIKAT / BIKIN AKUN KE-2 ===
 $dupeH=['bikin.*akun.*lagi','bikin.*akun.*baru','bikin.*akun.*2','bikin.*id.*lagi','bikin.*id.*baru','bikin.*id.*kedua','bikin.*id.*ke.*2','buat.*akun.*lagi','buat.*akun.*baru','buat.*akun.*kedua','buat.*akun.*2','daftar.*lagi','daftar.*ulang','akun.*kedua','akun.*ke.*2','id.*kedua','id.*ke.*2','id.*baru','punya.*akun.*2','dua.*akun','2.*akun','nomor.*sama.*udah.*ada','hp.*udah.*terdaftar','nomor.*udah.*terdaftar','kenapa.*ga.*bisa.*daftar','tidak.*bisa.*daftar'];
 foreach($dupeH as $p)if(preg_match('/'.$p.'/u',$t))return'akun_dupe';

 return null;
}

// ─── TEMPLATE BALASAN PER TOPIK + ESCALATION ───
// $count = berapa kali user bahas topik ini di sesi
// Reply jadi lebih tegas kalo user spam topik sama
function getTemplateReply($topic,$count){
 $T=[
'depo_gagal'=>[
 // 1st reply — sopan & informatif
"Halo kak \n\nUntuk deposit yang belum masuk, sistem kami sudah <b>OTOMATIS</b> mengecek status pembayaran ke gateway secara berkala.\n\nKemungkinan penyebab:\n• <b>Transfer melebihi batas waktu (12 jam)</b> → otomatis EXPIRED\n• Nominal yang dibayar tidak sesuai (bukan pay_amount yang tertera)\n• Pembayaran gagal di e-wallet/bank kakak\n\n<b>Jika benar sudah bayar tepat waktu & nominal sesuai</b>, saldo akan otomatis masuk dalam 1-2 menit. Silakan refresh halaman.\n\nUntuk hal di luar kondisi tsb, mohon maaf kami tidak dapat membantu manual karena sistem kami sudah otomatis",
 // 2nd reply — lebih tegas
"Mohon maaf kak \n\nSeperti yang sudah dijelaskan, sistem deposit kami <b>FULL OTOMATIS</b>. Jika saldo tidak masuk artinya:\n\n1. Pembayaran <b>gagal</b> atau <b>terlambat</b> dari batas waktu\n2. Nominal tidak sesuai\n\nKami <b>tidak dapat melakukan input manual</b> untuk kasus ini. Silakan kakak coba deposit ulang dengan memperhatikan:\n• Nominal harus PERSIS (lihat angka yang muncul)\n• Bayar sebelum timer habis (12 jam)\n\nTerima kasih atas pengertiannya",
 // 3rd+ reply — tegas final
"Mohon maaf kak, untuk kasus deposit yang dipertanyakan ini sudah kami jelaskan 2x sebelumnya. Sistem kami sudah otomatis dan tidak dapat melakukan kredit manual.\n\nSilakan langsung coba deposit ulang. Pesan terkait topik yang sama tidak akan kami balas berulang. Terima kasih"
 ],
'wd_gagal'=>[
"Halo kak \n\nUntuk withdraw yang masih pending:\n• Proses WD <b>maksimal 5-15 menit</b> di jam operasional\n• Jam sibuk bisa lebih lama (max 1 jam)\n• Pastikan nomor rekening sudah benar\n• Pastikan turnover sudah terpenuhi (cek di profil)\n\nKalau WD ditolak, saldo otomatis dikembalikan & ada notif memo dengan alasannya. Mohon ditunggu dengan sabar",
"Mohon maaf kak, untuk WD pending mohon ditunggu sampai max 1 jam di jam sibuk. Sistem proses berurutan sesuai antrian.\n\nKalau lewat 1 jam masih pending, baru hubungi kami lagi dengan info: nominal & jam request WD. Terima kasih",
"Mohon ditunggu kak, sudah dijelaskan max 1 jam. Pesan diulang tidak mempercepat antrian. Terima kasih"
 ],
'depo_cara'=>[
"Cara Deposit:\n\n1. Klik menu <b>Deposit</b> di bawah\n2. Pilih nominal (min sesuai pengaturan)\n3. Pilih metode: <b>QRIS / DANA / OVO / GoPay / ShopeePay / BCA / BRI / Mandiri / Permata</b>\n4. Bayar SESUAI nominal yang muncul (PERSIS, ada kode unik)\n5. Saldo otomatis masuk dalam 1-2 menit\n\n<b>PENTING:</b>\n• Bayar sebelum timer 12 jam habis\n• Maksimal 3 deposit pending sekaligus\n\nSelamat bermain!\n\n#btn Deposit Disini|deposit.php",
"Sudah dijelaskan ya kak di atas. Silakan langsung klik tombol di bawah.\n\n#btn Deposit Disini|deposit.php"
 ],
'wd_cara'=>[
"Cara Withdraw:\n\n1. Klik menu <b>Penarikan</b> di bawah\n2. Pastikan rekening sudah ditambahkan\n3. Masukkan nominal (min sesuai aturan)\n4. Submit & tunggu proses (5-15 menit jam normal, max 1 jam jam sibuk)\n\n<b>SYARAT WD:</b>\n• Turnover bonus harus terpenuhi (kalau pakai bonus depo)\n• Tidak boleh ada WD pending lain\n• Nominal di atas minimum\n\nCek turnover di menu Profil\n\n#btn Tarik Saldo Disini|withdraw.php",
"Sudah dijelaskan di atas ya kak.\n\n#btn Tarik Saldo Disini|withdraw.php"
 ],
'game_error'=>[
"Halo kak \n\nUntuk game yang bermasalah:\n• Coba <b>refresh / reload</b> halaman\n• Pastikan koneksi internet stabil\n• Coba game lain di provider yang sama\n• Coba browser/aplikasi lain\n• Tarik dulu saldo dari game (jika ada), baru main lagi\n\nKalau masalah dari sisi provider, biasanya kembali normal dalam beberapa menit. Game error tidak menghilangkan saldo kakak — semua bet dicatat di provider",
"Sudah dijelaskan langkahnya ya kak. Kalau masih bermasalah, coba provider/game lain dulu sambil menunggu provider tsb normal",
"Mohon maaf kak, untuk game error dari sisi provider kami tidak dapat memperbaiki manual. Silakan coba game/provider lain. Terima kasih"
 ],
'login'=>[
"Untuk masalah akun:\n• <b>Lupa password</b>: Hubungi CS dengan menyertakan nomor HP terdaftar untuk reset\n• <b>Akun terkunci</b>: Biasanya karena salah password berkali-kali, tunggu 30 menit atau hubungi CS\n• <b>Daftar</b>: Klik tombol Daftar di home, isi nomor HP & password\n\nTerima kasih",
"Sudah dijelaskan di atas ya kak. Kalau lupa password, sebutkan nomor HP terdaftar untuk kami bantu reset"
 ],
'bonus'=>[
"Untuk bonus:\n• <b>Bonus deposit</b>: Otomatis masuk saat deposit berhasil (jika dipilih saat depo)\n• <b>Referral</b>: Otomatis masuk saat downline deposit pertama\n• <b>Hadiah Undangan</b>: Klaim manual di menu Undang Teman, syarat lihat di sana\n• <b>Rebate</b>: Diproses setiap minggu otomatis\n\nKalau bonus belum masuk, cek di menu Profil → Riwayat untuk detail status",
"Sudah dijelaskan di atas ya kak. Cek menu Profil → Riwayat untuk lihat detail bonus"
 ],
'rekening'=>[
"Untuk ubah/tambah rekening, silakan ke menu <b>Profil → Rekening Bank</b>. Bisa tambahkan rekening baru atau pakai yang sudah ada.\n\nUntuk ubah <b>nama akun</b>, mohon maaf tidak bisa diubah karena terikat dengan data verifikasi awal.\n\nTerima kasih",
"Sudah dijelaskan di atas ya kak"
 ],
'akun_dupe'=>[
// 1st reply — sopan
"Halo kak\n\nMohon maaf, sistem kami menerapkan kebijakan <b>1 NOMOR HP = 1 AKUN</b>. Tidak diperbolehkan membuat akun lebih dari satu dengan nomor HP atau data yang sama.\n\nKebijakan ini berlaku untuk:\n- 1 orang = 1 akun\n- 1 nomor HP = 1 akun\n- 1 rekening bank = 1 akun\n\nKalau kakak lupa password akun lama, silakan sebutkan nomor HP terdaftar untuk kami bantu reset. Bukan bikin akun baru.\n\nTerima kasih",
// 2nd reply — lebih tegas
"Mohon maaf kak\n\nSudah dijelaskan: <b>sistem kami HANYA MENGIZINKAN 1 AKUN per nomor HP / per orang</b>. Kebijakan ini tidak bisa dinegosiasikan.\n\nMembuat multi-akun (akun ke-2, ke-3, dst) termasuk pelanggaran dan akan berakibat:\n- Semua akun di-BANNED permanen\n- Saldo di dalam akun HANGUS\n- Tidak bisa withdraw\n\nKalau kakak punya masalah dengan akun lama, jelaskan masalahnya. Jangan bikin akun baru. Terima kasih",
// 3rd+ reply — final
"Mohon maaf kak, sudah dijelaskan 2x sebelumnya. Kebijakan <b>1 akun per orang</b> adalah FINAL.\n\nKalau kakak masih memaksa bikin akun kedua, akun-akun kakak (termasuk yang lama) akan kami BANNED permanen tanpa pemberitahuan lebih lanjut. Terima kasih"
 ],
 ];
 if(!isset($T[$topic]))return null;
 $arr=$T[$topic];
 // count starts from 1; clamp ke index terakhir kalau lebih
 $idx=min(max(0,$count-1),count($arr)-1);
 return $arr[$idx];
}

// ─── DATA-AWARE REPLY untuk depo_gagal — ambil riwayat deposit user real ───
// Return reply dengan FAKTA dari DB. User ga bisa ngeyel kalau dikasih bukti.
function getDepoGagalReplyWithData($db,$uid,$count){
 if(!$uid){
 // Tamu (harusnya udah ga bisa karena wajib login)
 return getTemplateReply('depo_gagal',$count);
 }
 // Ambil 5 deposit terakhir user
 $q=$db->prepare("SELECT id,tx_id,nominal,status,method,created_at,paid_at,expires_at
 FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
 $q->execute([$uid]);
 $deps=$q->fetchAll();

 // Ambil saldo current
 $bal=$db->prepare("SELECT balance,total_deposit FROM users WHERE id=?");
 $bal->execute([$uid]);$u=$bal->fetch();

 // Build reply berdasarkan situasi nyata
 $reply='';

 // ═══ KASUS 1: User TIDAK PUNYA RIWAYAT DEPOSIT SAMA SEKALI ═══
 if(!$deps){
 if($count<=1){
 $reply ="Halo kak \n\n";
 $reply.="Berdasarkan data sistem kami, akun kakak <b>BELUM PERNAH MELAKUKAN DEPOSIT SAMA SEKALI</b>.\n\n";
 $reply.="<b>Data akun kakak:</b>\n";
 $reply.="• Total Deposit: Rp 0\n";
 $reply.="• Saldo:".idr(intval($u['balance']??0))."\n";
 $reply.="• Riwayat Deposit: <b>KOSONG</b>\n\n";
 $reply.="Kalau kakak mau bermain, silakan deposit dulu via menu <b>Deposit</b> di bawah. Kalau kakak merasa sudah pernah bayar tapi datanya kosong, mungkin kakak salah tujuan transfer atau salah akun. Cek lagi ya";
 }elseif($count==2){
 $reply ="Mohon maaf kak \n\n";
 $reply.="Sudah dijelaskan sebelumnya: <b>akun kakak TIDAK PUNYA RIWAYAT DEPOSIT</b>.\n\n";
 $reply.="Sistem kami mencatat semua transaksi otomatis. Tidak mungkin ada deposit yang \"tidak tercatat\". Kalau kakak transfer ke rekening yang bukan dari sistem kami, itu di luar tanggung jawab kami.\n\n";
 $reply.="Silakan deposit dengan benar via menu <b>Deposit</b> dan ikuti instruksi pembayaran";
 }else{
 // Level 3+ → peringatan
 $reply ="<b>PERINGATAN KE-".($count-2)."</b>\n\n";
 $reply.="Kakak sudah".$count."x mengeluhkan deposit padahal <b>RIWAYAT DEPOSIT KAKAK KOSONG</b>.\n\n";
 $reply.="Pesan keluhan tanpa dasar yang dilakukan berulang dapat dianggap sebagai SPAM dan akun bisa di-<b>BANNED</b> permanen.\n\n";
 if($count>=5){
 $reply.="<b>AKUN ANDA AKAN DIBANNED OTOMATIS</b> jika spam berlanjut. Silakan stop kirim pesan keluhan tanpa dasar.";
 }else{
 $reply.="Mohon stop spam keluhan. Kalau benar mau bermain, langsung deposit. Terima kasih";
 }
 }
 return $reply;
 }

 // ═══ KASUS 2: PUNYA DEPOSIT — kasih data nyata ═══
 $latest=$deps[0];
 $latestStatus=$latest['status'];
 $hasPending=false;$hasExpired=false;$hasPaid=false;
 foreach($deps as $d){
 if($d['status']==='pending')$hasPending=true;
 if(in_array($d['status'],['expired','failed','cancelled']))$hasExpired=true;
 if($d['status']==='paid')$hasPaid=true;
 }

 if($count<=1){
 $reply ="Halo kak \n\n";
 $reply.="Saya udah cek riwayat deposit kakak. Ini data 5 transaksi terakhir:\n\n";
 $reply.="<pre>";
 foreach($deps as $i=>$d){
 $statusLbl=[
'paid'=>'SUKSES',
'pending'=>'⏳ MENUNGGU',
'expired'=>'EXPIRED',
'failed'=>'GAGAL',
'cancelled'=>'DIBATAL'
 ][$d['status']]??$d['status'];
 $tgl=date('d M H:i',strtotime($d['created_at']));
 $reply.=($i+1).". $tgl • Rp".number_format(intval($d['nominal']),0,',','.')."•".strtoupper($d['method']??'qris')."• $statusLbl\n";
 }
 $reply.="</pre>\n";

 // Analisis berdasarkan pattern
 if($latestStatus==='pending'){
 $expIn=$latest['expires_at']?max(0,strtotime($latest['expires_at'])-time()):0;
 if($expIn>0){
 // Format sisa waktu: kalau >1 jam tampil "X jam Y menit", <1 jam tampil "Y menit"
 $hh=floor($expIn/3600);$mm=floor(($expIn%3600)/60);
 $waktuStr=$hh>0?($hh.' jam '.$mm.' menit'):($mm.' menit');
 $reply.="<b>Deposit terakhir kakak masih PENDING</b>, silakan lanjutkan pembayaran (sisa waktu: ".$waktuStr.").\n\n";
 $reply.="Sistem akan otomatis credit saldo dalam 1-2 menit setelah pembayaran berhasil";
 }else{
 $reply.="<b>Deposit terakhir kakak sudah lewat batas waktu pembayaran</b>. Akan otomatis EXPIRED.\n\n";
 $reply.="Silakan deposit ulang ya kak";
 }
 }elseif($latestStatus==='expired'||$latestStatus==='failed'||$latestStatus==='cancelled'){
 $reply.="<b>Deposit terakhir kakak status:".strtoupper($latestStatus)."</b>.\n\n";
 $reply.="Artinya pembayaran <b>tidak diterima</b> oleh sistem (transfer telat / nominal salah / batal). Saldo TIDAK akan masuk untuk transaksi yang sudah expired/gagal.\n\n";
 $reply.="Silakan deposit ULANG dan pastikan:\n";
 $reply.="• Bayar SEBELUM timer 12 jam habis\n";
 $reply.="• Nominal PERSIS sesuai pay_amount yang muncul\n\nTerima kasih";
 }elseif($latestStatus==='paid'){
 $reply.="<b>Deposit terakhir kakak sudah SUKSES</b> & saldo masuk.\n\n";
 $reply.="Saldo kakak saat ini: <b>".idr(intval($u['balance']??0))."</b>\n\n";
 $reply.="Kalau saldo kakak terasa kurang, mungkin sudah dipakai untuk bermain. Cek riwayat di menu <b>Profil → Riwayat</b>";
 }
 }elseif($count==2){
 $reply ="Mohon maaf kak \n\n";
 $reply.="Sudah dijelaskan dengan DATA NYATA dari sistem. Status deposit kakak ada di atas dan SISTEM SUDAH OTOMATIS.\n\n";
 if($hasExpired&&!$hasPending){
 $reply.="Kakak punya".count($deps)."deposit terakhir, beberapa di antaranya EXPIRED/GAGAL. Itu artinya pembayaran tidak diterima sistem.\n\n";
 $reply.="<b>Kami TIDAK BISA memasukkan saldo manual</b> untuk deposit yang sudah expired/gagal. Silakan deposit ulang dengan benar";
 }elseif($latestStatus==='paid'){
 $reply.="Deposit terakhir kakak <b>SUDAH SUKSES</b> dan saldo SUDAH MASUK (".idr(intval($u['balance']??0)).").\n\n";
 $reply.="Kalau kakak merasa ada deposit lain yang belum masuk, itu tidak ada di catatan kami";
 }else{
 $reply.="Mohon maaf kami tidak bisa membantu lebih lanjut. Silakan ikuti instruksi yang sudah diberikan";
 }
 }else{
 // Level 3+ → peringatan keras
 $reply ="<b>PERINGATAN KE-".($count-2)."</b>\n\n";
 $reply.="Kakak sudah".$count."x menanyakan hal yang sama padahal <b>DATA SUDAH JELAS</b> di pesan-pesan sebelumnya.\n\n";
 if($count>=5){
 $reply.="<b>AKUN AKAN DIBAN OTOMATIS</b> jika spam keluhan tanpa dasar berlanjut.\n\n";
 $reply.="Silakan baca ulang penjelasan & status deposit kakak di atas. Kami tidak akan menjawab pertanyaan yang sama berulang.";
 }else{
 $reply.="Mohon stop spam pesan keluhan. Kalau memang ada masalah baru, jelaskan dengan detail. Kami tidak akan menjawab pertanyaan berulang. Terima kasih";
 }
 }
 return $reply;
}

// ─── AUTO-BAN saat user spam keluhan tanpa dasar ───
function autoBanIfSpamming($db,$uid,$sessId,$topic,$count){
 if(!$uid)return false;
 if($count<6)return false; // baru ban di level 6+
 // Cek user belum di-ban
 $u=$db->prepare("SELECT status FROM users WHERE id=?");
 $u->execute([$uid]);$status=$u->fetchColumn();
 if($status==='banned')return false; // sudah di-ban
 // Khusus depo_gagal: cek dia BENERAN ga punya deposit valid
 if($topic==='depo_gagal'){
 $depCheck=$db->prepare("SELECT COUNT(*) FROM deposits WHERE user_id=? AND status IN('paid','pending')");
 $depCheck->execute([$uid]);
 $hasValidDep=intval($depCheck->fetchColumn());
 // Kalau punya deposit valid (paid/pending) — JANGAN ban, mungkin ada masalah real
 if($hasValidDep>0)return false;
 }
 // BAN!
 try{
 $db->prepare("UPDATE users SET status='banned'WHERE id=?")->execute([$uid]);
 @file_put_contents(__DIR__.'/../chat_ban_log.txt',
 date('Y-m-d H:i:s')."AUTO_BAN uid=$uid sess=$sessId topic=$topic count=$count\n",
 FILE_APPEND);
 return true;
 }catch(Exception $e){return false;}
}

// ─── WEBHOOK FROM TELEGRAM ───
if($action==='webhook'){
 $raw=file_get_contents('php://input');
 @file_put_contents(__DIR__.'/../tg_webhook.log',date('Y-m-d H:i:s').''.$raw."\n",FILE_APPEND);
 $upd=json_decode($raw,true);
 $msg=$upd['message']??null;
 if(!$msg){http_response_code(200);echo'ok';exit;}
 
 $settings=getBotSettings($db);
 $adminChatId=$settings['bot_admin_chat_id']??'';
 $fromId=$msg['from']['id']??0;
 $chatId=$msg['chat']['id']??0; // tempat pesan ngirim (group/user/channel)
 
 // Accept messages kalau bot_admin_chat_id match SALAH SATU dari:
 // - chat.id (pesan dari group/channel yg di-set sebagai admin)
 // - from.id (pesan DM langsung dari admin user)
 // Kalau bot_admin_chat_id belum di-set (kosong), terima semua (open mode)
 if($adminChatId){
 $adminMatch = ($chatId==$adminChatId || $fromId==$adminChatId);
 if(!$adminMatch){
 @file_put_contents(__DIR__.'/../tg_webhook.log',date('Y-m-d H:i:s')."REJECTED: fromId=$fromId chatId=$chatId adminChatId=$adminChatId\n",FILE_APPEND);
 http_response_code(200);echo'ok';exit;
 }
 }
 // Karena bisa group, semua reply bot pake $chatId (bukan $adminChatId)
 // supaya auto-reply di group yg sama, bukan spam ke DM admin
 if($chatId)$adminChatId=$chatId;
 
 $replyToId=$msg['reply_to_message']['message_id']??null;
 $text=$msg['text']??$msg['caption']??'';
 $photo=null;
 if(!empty($msg['photo'])){
 $largest=end($msg['photo']);
 $fileId=$largest['file_id']??'';
 if($fileId){
 $token=$settings['bot_token']??'';
 $fi=json_decode(@file_get_contents("https://api.telegram.org/bot$token/getFile?file_id=$fileId"),true);
 $fpath=$fi['result']['file_path']??'';
 if($fpath){
   // DOWNLOAD ke lokal biar ga expired (URL TG cuma valid 1 jam)
   $tgUrl="https://api.telegram.org/file/bot$token/$fpath";
   try{
     $dir=__DIR__.'/../asset/uploads/chat/';
     if(!is_dir($dir))@mkdir($dir,0755,true);
     $ext=pathinfo($fpath,PATHINFO_EXTENSION)?:'jpg';
     $name='tg_'.date('Ymd').'_'.time().'_'.rand(1000,9999).'.'.$ext;
     $dest=$dir.$name;
     $imgData=@file_get_contents($tgUrl);
     if($imgData && @file_put_contents($dest,$imgData)){
       $scheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
       $host=$_SERVER['HTTP_HOST']??'';
       $photo=$host?"$scheme://$host/asset/uploads/chat/$name":"asset/uploads/chat/$name";
     }else{
       // Fallback: pakai URL TG langsung (bakal expired 1 jam, tapi tampil)
       $photo=$tgUrl;
     }
   }catch(Exception $e){$photo=$tgUrl;}
 }
 }
 }
 $token=$settings['bot_token']??'';
 
 // ═══════════════════════════════════════════════
 // COMMANDS (/-prefix): prioritaskan command duluan
 // ═══════════════════════════════════════════════
 $t=trim($text);

 // /help atau /start
 if(in_array($t,['/help','/start','/?'])){
 $h ="<b>Admin Bot</b>\n━━━━━━━━━━━━━━\n\n";
 $h.="<b>CHAT</b>\n";
 $h.="Reply pesan user dari bot → langsung terkirim.\n";
 $h.="Kirim foto dengan caption, di-reply ke pesan user, juga auto forward.\n";
 $h.="<code>/list</code> - sesi aktif\n";
 $h.="<code>/close [SID]</code> - tutup sesi\n\n";
 $h.="<b>DEPOSIT</b>\n";
 $h.="<code>/dep</code> - list pending + expired\n";
 $h.="<code>/approve ID</code> - approve (force, walau expired)\n";
 $h.="<code>/expire ID</code> - paksa expired\n";
 $h.="<code>/revert ID</code> - balikin expired → pending\n";
 $h.="<code>/reject ID [alasan]</code> - tolak\n\n";
 $h.="<b>WITHDRAW</b>\n";
 $h.="<code>/wd</code> - list pending\n";
 $h.="<code>/wd_approve ID</code> - approve\n";
 $h.="<code>/wd_reject ID [alasan]</code> - tolak (refund)\n\n";
 $h.="<b>USER</b>\n";
 $h.="<code>/user [username]</code> - info + saldo\n";
 $h.="<code>/saldo [username] +50000</code> - adjust saldo\n";
 $h.="<code>/ban [username]</code> - banned user\n";
 $h.="<code>/unban [username]</code> - buka banned\n";
 $h.="<code>/resetpw [username]</code> - reset password\n\n";
 $h.="<b>GAME</b>\n";
 $h.="<code>/agent</code> - saldo agent NexusGGR\n";
 $h.="<code>/game [username]</code> - saldo user di game\n\n";
 $h.="<b>STATS</b>\n";
 $h.="<code>/stats</code> - hari ini (deposit/withdraw/user baru)\n";
 $h.="<code>/top</code> - top 10 depositor hari ini\n";
 tgSend($token,$adminChatId,$h);
 http_response_code(200);echo'ok';exit;
 }

 // /list — semua sesi aktif
 if($t==='/list'){
 $list=$db->query("SELECT s.id,s.username,s.user_id,COUNT(CASE WHEN m.sender='user'AND m.is_read=0 THEN 1 END) as unread,MAX(m.created_at) as last FROM chat_sessions s LEFT JOIN chat_messages m ON m.session_id=s.id WHERE s.status='open'GROUP BY s.id ORDER BY s.last_at DESC LIMIT 20")->fetchAll();
 if(empty($list)){
 tgSend($token,$adminChatId,"Tidak ada sesi aktif.");
 }else{
 $r="<b>Sesi Aktif (".count($list).")</b>\n---------------\n";
 foreach($list as $ls){
 $u=$ls['user_id']?"ID:".$ls['user_id']:"Tamu";
 $unread=$ls['unread']>0?"".$ls['unread']:"";
 $r.="<b>#".$ls['id']."</b>".htmlspecialchars($ls['username'])."($u)$unread\n";
 $r.="<code>/r".$ls['id']."pesan</code>\n";
 }
 tgSend($token,$adminChatId,$r);
 }
 http_response_code(200);echo'ok';exit;
 }

 // /close [SID]
 if(preg_match('/^\/close\s+(\d+)/i',$t,$m)){
 $db->prepare("UPDATE chat_sessions SET status='closed'WHERE id=?")->execute([$m[1]]);
 tgSend($token,$adminChatId,"Sesi #{$m[1]} ditutup.");
 http_response_code(200);echo'ok';exit;
 }

 // ─── /dep — list pending + expired deposits (yang butuh action admin)
 if($t==='/dep'){
 try{
 // Auto-expire stale pending (lewat 30 menit dari expires_at) sebelum list
 try{
 $db->exec("UPDATE deposits SET status='expired'
 WHERE status='pending'AND expires_at IS NOT NULL
 AND expires_at < NOW() - INTERVAL 24 HOUR");
 }catch(Exception $e){}

 $deps=$db->query("SELECT d.id,d.user_id,d.nominal,d.method,d.status,d.created_at,d.expires_at,u.username
 FROM deposits d LEFT JOIN users u ON u.id=d.user_id
 WHERE d.status IN ('pending','expired')
 ORDER BY d.status DESC,d.created_at DESC LIMIT 20")->fetchAll();
 if(empty($deps)){
 tgSend($token,$adminChatId,"Tidak ada deposit pending atau expired.");
 }else{
 $pendCount=0;$expCount=0;foreach($deps as $dd){if($dd['status']==='pending')$pendCount++;else $expCount++;}
 $r="<b>DEPOSIT NEEDS ACTION</b>\nPending: $pendCount • Expired: $expCount\n---------------\n";
 foreach($deps as $dd){
 $icon=$dd['status']==='pending'?'':'';
 $r.="$icon <b>#".$dd['id']."</b>".htmlspecialchars($dd['username']??'?')."•".idr($dd['nominal'])."\n";
 $r.="".htmlspecialchars($dd['method']??'qris').'•'.date('d/m H:i',strtotime($dd['created_at']))."• <i>".$dd['status']."</i>\n";
 if($dd['status']==='pending'){
 $r.="<code>/approve".$dd['id']."</code> <code>/expire".$dd['id']."</code> <code>/reject".$dd['id']."alasan</code>\n";
 } else {
 $r.="<code>/approve".$dd['id']."</code> <code>/revert".$dd['id']."</code> <code>/reject".$dd['id']."alasan</code>\n";
 }
 }
 tgSend($token,$adminChatId,$r);
 }
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /approve [ID] — force approve deposit (pending ATAU expired/failed/cancelled)
 if(preg_match('/^\/approve\s+(\d+)/i',$t,$m)){
 $dId=intval($m[1]);
 try{
 require_once __DIR__.'/../includes/deposit_lib.php';
 $chk=$db->prepare("SELECT * FROM deposits WHERE id=?");$chk->execute([$dId]);$dep=$chk->fetch();
 if(!$dep){tgSend($token,$adminChatId,"Deposit #$dId tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if($dep['status']==='paid'){tgSend($token,$adminChatId,"Deposit #$dId sudah <b>paid</b>.");http_response_code(200);echo'ok';exit;}
 if(!in_array($dep['status'],['pending','expired','failed','cancelled'])){
 tgSend($token,$adminChatId,"Deposit #$dId status <b>".$dep['status']."</b> tidak bisa di-approve.");
 http_response_code(200);echo'ok';exit;
 }
 $wasStatus=$dep['status'];
 $ok=creditDeposit($db,$dep,null,'tg_force_'.$wasStatus);
 if($ok){
 $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$dep['user_id']]);$un=$u->fetchColumn();
 $forceTag=$wasStatus==='pending'?'':'<i>(force dari'.$wasStatus.')</i>';
 tgSend($token,$adminChatId,"<b>Deposit #$dId approved</b>$forceTag\nUser: $un\nNominal:".idr($dep['nominal']));
 }else{
 tgSend($token,$adminChatId,"Gagal approve deposit #$dId (cek deposit_log.txt)");
 }
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /expire [ID] — mark pending → expired tanpa credit
 if(preg_match('/^\/expire\s+(\d+)/i',$t,$m)){
 $dId=intval($m[1]);
 try{
 $chk=$db->prepare("SELECT * FROM deposits WHERE id=?");$chk->execute([$dId]);$dep=$chk->fetch();
 if(!$dep){tgSend($token,$adminChatId,"Deposit #$dId tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if($dep['status']!=='pending'){tgSend($token,$adminChatId,"Deposit #$dId status <b>".$dep['status']."</b> (bukan pending).");http_response_code(200);echo'ok';exit;}
 $db->prepare("UPDATE deposits SET status='expired'WHERE id=?")->execute([$dId]);
 $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$dep['user_id']]);$un=$u->fetchColumn();
 tgSend($token,$adminChatId,"⏱ <b>Deposit #$dId expired</b>\nUser: $un\nNominal:".idr($dep['nominal']));
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /revert [ID] — balikin expired/failed/cancelled → pending + extend 12 jam
 if(preg_match('/^\/revert\s+(\d+)/i',$t,$m)){
 $dId=intval($m[1]);
 try{
 $chk=$db->prepare("SELECT * FROM deposits WHERE id=?");$chk->execute([$dId]);$dep=$chk->fetch();
 if(!$dep){tgSend($token,$adminChatId,"Deposit #$dId tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if(!in_array($dep['status'],['expired','failed','cancelled'])){
 tgSend($token,$adminChatId,"Deposit #$dId status <b>".$dep['status']."</b> tidak bisa di-revert.");
 http_response_code(200);echo'ok';exit;
 }
 $newExp=date('Y-m-d H:i:s',strtotime('+12 hours'));
 $db->prepare("UPDATE deposits SET status='pending',expires_at=? WHERE id=?")->execute([$newExp,$dId]);
 $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$dep['user_id']]);$un=$u->fetchColumn();
 tgSend($token,$adminChatId,"↺ <b>Deposit #$dId revert ke pending</b>\nUser: $un\nNominal:".idr($dep['nominal'])."\nExpires: $newExp");
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /reject [ID] [alasan] — work untuk pending ATAU expired (paksa failed)
 if(preg_match('/^\/reject\s+(\d+)(?:\s+(.+))?/is',$t,$m)){
 $dId=intval($m[1]);$reason=trim($m[2]??'Ditolak admin');
 try{
 $chk=$db->prepare("SELECT * FROM deposits WHERE id=?");$chk->execute([$dId]);$dep=$chk->fetch();
 if(!$dep){tgSend($token,$adminChatId,"Deposit #$dId tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if(!in_array($dep['status'],['pending','expired'])){
 tgSend($token,$adminChatId,"Deposit sudah <b>".$dep['status']."</b>.");http_response_code(200);echo'ok';exit;
 }
 $db->prepare("UPDATE deposits SET status='failed',note=? WHERE id=?")->execute([$reason,$dId]);
 // Notify user via memo
 try{
 $db->exec("CREATE TABLE IF NOT EXISTS memos(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,type VARCHAR(20),title VARCHAR(200),body TEXT,to_user_id INT UNSIGNED,is_read TINYINT DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
 $db->prepare("INSERT INTO memos(type,title,body,to_user_id) VALUES('target','Deposit Ditolak',?,?)")->execute(['Alasan:'.$reason,$dep['user_id']]);
 }catch(Exception $e){}
 tgSend($token,$adminChatId,"Deposit #$dId <b>ditolak</b>\nAlasan:".htmlspecialchars($reason));
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /wd — list pending withdraw
 if($t==='/wd'){
 try{
 $wds=$db->query("SELECT w.id,w.user_id,w.amount,w.bank_name,w.account_number,w.account_name,w.created_at,u.username FROM withdrawals w LEFT JOIN users u ON u.id=w.user_id WHERE w.status='pending'ORDER BY w.created_at DESC LIMIT 20")->fetchAll();
 if(empty($wds)){
 tgSend($token,$adminChatId,"Tidak ada withdraw pending.");
 }else{
 $r="<b>WITHDRAW PENDING (".count($wds).")</b>\n---------------\n";
 foreach($wds as $w){
 $r.="<b>#".$w['id']."</b>".htmlspecialchars($w['username']??'?')."•".idr($w['amount'])."\n";
 $r.="".htmlspecialchars($w['bank_name']??'')."•".htmlspecialchars($w['account_number']??'')."\n";
 $r.="a/n".htmlspecialchars($w['account_name']??'')."\n";
 $r.="<code>/wd_approve".$w['id']."</code> <code>/wd_reject".$w['id']."alasan</code>\n";
 }
 tgSend($token,$adminChatId,$r);
 }
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /wd_approve [ID]
 if(preg_match('/^\/wd_approve\s+(\d+)/i',$t,$m)){
 $wId=intval($m[1]);
 try{
 $chk=$db->prepare("SELECT * FROM withdrawals WHERE id=?");$chk->execute([$wId]);$wd=$chk->fetch();
 if(!$wd){tgSend($token,$adminChatId,"Withdraw #$wId tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if($wd['status']!=='pending'){tgSend($token,$adminChatId,"Withdraw sudah <b>".$wd['status']."</b>.");http_response_code(200);echo'ok';exit;}
 $db->prepare("UPDATE withdrawals SET status='paid',processed_at=NOW() WHERE id=?")->execute([$wId]);
 $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$wd['user_id']]);$un=$u->fetchColumn();
 // Memo user
 try{$db->prepare("INSERT INTO memos(type,title,content,to_user_id) VALUES('target','Withdraw Berhasil',?,?)")->execute(['Withdraw Rp'.number_format($wd['amount'],0,',','.').'telah ditransfer ke rekening Anda.',$wd['user_id']]);}catch(Exception $e){}
 tgSend($token,$adminChatId,"<b>Withdraw #$wId approved</b>\nUser: $un\nNominal:".idr($wd['amount']));
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /wd_reject [ID] [alasan] — refund saldo
 if(preg_match('/^\/wd_reject\s+(\d+)(?:\s+(.+))?/is',$t,$m)){
 $wId=intval($m[1]);$reason=trim($m[2]??'Ditolak admin');
 try{
 $db->beginTransaction();
 $chk=$db->prepare("SELECT * FROM withdrawals WHERE id=? FOR UPDATE");$chk->execute([$wId]);$wd=$chk->fetch();
 if(!$wd){$db->rollBack();tgSend($token,$adminChatId,"Withdraw #$wId tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if($wd['status']!=='pending'){$db->rollBack();tgSend($token,$adminChatId,"Withdraw sudah <b>".$wd['status']."</b>.");http_response_code(200);echo'ok';exit;}
 // Refund saldo
 $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$wd['amount'],$wd['user_id']]);
 $db->prepare("UPDATE withdrawals SET status='rejected',note=?,processed_at=NOW() WHERE id=?")->execute([$reason,$wId]);
 // Log tx
 $b=$db->prepare("SELECT balance FROM users WHERE id=?");$b->execute([$wd['user_id']]);$bal=intval($b->fetchColumn());
 try{$db->prepare("INSERT INTO transactions(user_id,type,amount,balance_after,note) VALUES(?,?,?,?,?)")->execute([$wd['user_id'],'refund',$wd['amount'],$bal,'Refund withdraw ditolak:'.$reason]);}catch(Exception $e){}
 try{$db->prepare("INSERT INTO memos(type,title,content,to_user_id) VALUES('target','Withdraw Ditolak',?,?)")->execute(['Withdraw ditolak:'.$reason.'. Saldo telah dikembalikan.',$wd['user_id']]);}catch(Exception $e){}
 $db->commit();
 tgSend($token,$adminChatId,"Withdraw #$wId <b>ditolak</b>\nAlasan:".htmlspecialchars($reason)."\n Saldo user dikembalikan:".idr($wd['amount']));
 }catch(Exception $e){try{$db->rollBack();}catch(Exception $ee){}tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /user [username] — info user
 if(preg_match('/^\/user\s+(\S+)/i',$t,$m)){
 $un=trim($m[1]);
 try{
 $q=$db->prepare("SELECT id,username,phone,vip_level,balance,total_deposit,total_turnover,status,created_at FROM users WHERE username=? LIMIT 1");
 $q->execute([$un]);$u=$q->fetch();
 if(!$u){tgSend($token,$adminChatId,"User <code>$un</code> tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 $r ="<b>USER:".htmlspecialchars($u['username'])."</b>\n---------------\n";
 $r.="ID:".$u['id']."\n";
 if($u['phone'])$r.="".htmlspecialchars($u['phone'])."\n";
 $r.="VIP".intval($u['vip_level'])."\n";
 $r.="Saldo: <b>".idr($u['balance'])."</b>\n";
 $r.="Total depo:".idr($u['total_deposit'])."\n";
 $r.="Total TO:".idr($u['total_turnover'])."\n";
 $r.="Daftar:".date('d M Y',strtotime($u['created_at']))."\n";
 $r.="Status:".($u['status']??'active')."\n\n";
 $r.="<code>/saldo".$u['username']."+10000</code>\n";
 $r.="<code>/saldo".$u['username']."-10000</code>";
 tgSend($token,$adminChatId,$r);
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /saldo [username] [+/-][nominal] — adjust saldo
 if(preg_match('/^\/saldo\s+(\S+)\s+([+-]?\d+)/i',$t,$m)){
 $un=trim($m[1]);$delta=intval($m[2]);
 if($delta===0){tgSend($token,$adminChatId,"Nominal harus > 0 dengan prefix + atau -");http_response_code(200);echo'ok';exit;}
 try{
 $db->beginTransaction();
 $q=$db->prepare("SELECT id,username,balance FROM users WHERE username=? FOR UPDATE");$q->execute([$un]);$u=$q->fetch();
 if(!$u){$db->rollBack();tgSend($token,$adminChatId,"User tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 $newBal=intval($u['balance'])+$delta;
 if($newBal<0){$db->rollBack();tgSend($token,$adminChatId,"Saldo tidak cukup untuk dikurangi. Saldo saat ini:".idr($u['balance']));http_response_code(200);echo'ok';exit;}
 $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$newBal,$u['id']]);
 $type=$delta>0?'admin_credit':'admin_debit';
 try{$db->prepare("INSERT INTO transactions(user_id,type,amount,balance_after,note) VALUES(?,?,?,?,?)")->execute([$u['id'],$type,abs($delta),$newBal,'Adjust saldo via Telegram admin']);}catch(Exception $e){}
 try{$db->prepare("INSERT INTO memos(type,title,content,to_user_id) VALUES('target',?,?,?)")->execute([$delta>0?'Saldo Ditambahkan':'Saldo Dikurangi',($delta>0?'+':'').'Rp'.number_format(abs($delta),0,',','.').'oleh admin. Saldo baru: Rp'.number_format($newBal,0,',','.'),$u['id']]);}catch(Exception $e){}
 $db->commit();
 tgSend($token,$adminChatId,"Saldo <b>$un</b>\n".($delta>0?'+':'').idr(abs($delta))."\nSaldo sekarang: <b>".idr($newBal)."</b>");
 }catch(Exception $e){try{$db->rollBack();}catch(Exception $ee){}tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /ban [username] — banned user (status=banned)
 if(preg_match('/^\/ban\s+(\S+)/i',$t,$m)){
 $un=trim($m[1]);
 try{
 // Auto-add column kalau belum ada
 try{$db->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT'active'");}catch(Exception $e){}
 $q=$db->prepare("SELECT id,username,status FROM users WHERE username=?");$q->execute([$un]);$u=$q->fetch();
 if(!$u){tgSend($token,$adminChatId,"User <code>$un</code> tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if(($u['status']??'active')==='banned'){tgSend($token,$adminChatId,"User <b>$un</b> sudah banned.");http_response_code(200);echo'ok';exit;}
 $db->prepare("UPDATE users SET status='banned'WHERE id=?")->execute([$u['id']]);
 // Kick session (hapus auth_token)
 try{$db->prepare("UPDATE users SET auth_token=NULL WHERE id=?")->execute([$u['id']]);}catch(Exception $e){}
 tgSend($token,$adminChatId,"User <b>$un</b> telah di-banned.\nSession login di-reset.");
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /unban [username] — buka banned
 if(preg_match('/^\/unban\s+(\S+)/i',$t,$m)){
 $un=trim($m[1]);
 try{
 try{$db->exec("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT'active'");}catch(Exception $e){}
 $q=$db->prepare("SELECT id,username,status FROM users WHERE username=?");$q->execute([$un]);$u=$q->fetch();
 if(!$u){tgSend($token,$adminChatId,"User <code>$un</code> tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 $db->prepare("UPDATE users SET status='active'WHERE id=?")->execute([$u['id']]);
 tgSend($token,$adminChatId,"User <b>$un</b> status'active'. Bisa login lagi.");
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /resetpw [username] — reset password ke nomor HP
 if(preg_match('/^\/resetpw\s+(\S+)/i',$t,$m)){
 $un=trim($m[1]);
 try{
 $q=$db->prepare("SELECT id,username,phone FROM users WHERE username=?");$q->execute([$un]);$u=$q->fetch();
 if(!$u){tgSend($token,$adminChatId,"User <code>$un</code> tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 // Password default = nomor HP (user wajib ganti sesudah login)
 $newPw=$u['phone']?:'123456';
 $hash=password_hash($newPw,PASSWORD_DEFAULT);
 $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash,$u['id']]);
 // Kick session
 try{$db->prepare("UPDATE users SET auth_token=NULL WHERE id=?")->execute([$u['id']]);}catch(Exception $e){}
 tgSend($token,$adminChatId,"Password <b>$un</b> di-reset ke: <code>$newPw</code>\nSession login di-reset. User harus login ulang.");
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /stats — ringkasan hari ini
 if($t==='/stats'){
 try{
 $today=date('Y-m-d');
 // Deposit hari ini
 $d=$db->prepare("SELECT COUNT(*) c,COALESCE(SUM(nominal),0) t FROM deposits WHERE status='paid'AND DATE(paid_at)=?");
 $d->execute([$today]);$dep=$d->fetch();
 // Withdraw hari ini
 $w=$db->prepare("SELECT COUNT(*) c,COALESCE(SUM(amount),0) t FROM withdrawals WHERE status='approved'AND DATE(created_at)=?");
 $w->execute([$today]);$wd=$w->fetch();
 // User baru hari ini
 $u=$db->prepare("SELECT COUNT(*) c FROM users WHERE DATE(created_at)=?");
 $u->execute([$today]);$uc=intval($u->fetchColumn());
 // Deposit pending (total outstanding)
 $p=$db->query("SELECT COUNT(*) c,COALESCE(SUM(nominal),0) t FROM deposits WHERE status='pending'")->fetch();
 // Withdraw pending
 $wp=$db->query("SELECT COUNT(*) c,COALESCE(SUM(amount),0) t FROM withdrawals WHERE status='pending'")->fetch();
 // Total user aktif
 $total=$db->query("SELECT COUNT(*) FROM users")->fetchColumn();

 $profit=intval($dep['t'])-intval($wd['t']);
 $profitIcon=$profit>=0?'':'';

 $r="<b>Stats".date('d/m/Y')."</b>\n━━━━━━━━━━━━━━\n";
 $r.="Deposit:".$dep['c']."x →".idr($dep['t'])."\n";
 $r.="Withdraw:".$wd['c']."x →".idr($wd['t'])."\n";
 $r.="$profitIcon Net:".idr($profit)."\n";
 $r.="User baru: $uc\n";
 $r.="\n<b>Outstanding</b>\n";
 $r.="⏳ Deposit pending:".$p['c']."x (".idr($p['t']).")\n";
 $r.="Withdraw pending:".$wp['c']."x (".idr($wp['t']).")\n";
 $r.="\n Total member: <b>$total</b>";
 tgSend($token,$adminChatId,$r);
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /top — top 10 depositor hari ini
 if($t==='/top'){
 try{
 $today=date('Y-m-d');
 $q=$db->prepare("SELECT u.username,u.phone,COUNT(*) cnt,COALESCE(SUM(d.nominal),0) total
 FROM deposits d JOIN users u ON u.id=d.user_id
 WHERE d.status='paid'AND DATE(d.paid_at)=?
 GROUP BY u.id ORDER BY total DESC LIMIT 10");
 $q->execute([$today]);$list=$q->fetchAll();
 if(empty($list)){
 tgSend($token,$adminChatId,"Belum ada deposit paid hari ini.");
 }else{
 $r="<b>Top Depositor".date('d/m/Y')."</b>\n━━━━━━━━━━━━━━\n";
 foreach($list as $i=>$x){
 $rank=$i+1;
 $medal=$rank===1?'':($rank===2?'':($rank===3?'':"#$rank"));
 $r.="$medal <b>".htmlspecialchars($x['username'])."</b> -".idr($x['total'])."(".$x['cnt']."x)\n";
 }
 tgSend($token,$adminChatId,$r);
 }
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /history [username] — riwayat deposit user (10 terakhir)
 if(preg_match('/^\/history\s+(\S+)/i',$t,$m)){
 $un=trim($m[1]);
 try{
 $q=$db->prepare("SELECT id FROM users WHERE username=? LIMIT 1");$q->execute([$un]);$uid=$q->fetchColumn();
 if(!$uid){tgSend($token,$adminChatId,"User <code>$un</code> tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 $deps=$db->prepare("SELECT id,nominal,method,status,note,created_at,paid_at FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 10");
 $deps->execute([$uid]);$list=$deps->fetchAll();
 if(empty($list)){
 tgSend($token,$adminChatId,"<b>$un</b> belum ada deposit.");
 }else{
 $r="<b>Riwayat Deposit: $un</b>\n---------------\n";
 foreach($list as $d){
 $status=$d['status'];
 $icon=$status==='paid'?'':($status==='pending'?'⏳':($status==='rejected'?'':''));
 $r.="$icon <b>#".$d['id']."</b>".idr($d['nominal'])."•".htmlspecialchars($d['method']??'qris')."\n";
 $r.="".$status."•".date('d/m H:i',strtotime($d['created_at']));
 if($status==='paid'&&$d['paid_at'])$r.="→".date('d/m H:i',strtotime($d['paid_at']));
 $r.="\n";
 if($d['note']&&$status==='rejected')$r.="<i>".htmlspecialchars(substr($d['note'],0,60))."</i>\n";
 }
 // Summary
 $sum=$db->prepare("SELECT COUNT(*) c,COALESCE(SUM(CASE WHEN status='paid'THEN nominal END),0) t FROM deposits WHERE user_id=?");
 $sum->execute([$uid]);$s=$sum->fetch();
 $r.="\n Total".$s['c']."transaksi, terkonfirmasi".idr($s['t']);
 tgSend($token,$adminChatId,$r);
 }
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /agent — cek saldo agent NexusGGR
 if($t==='/agent'){
 try{
 if(!defined('NEXUS_URL')||!defined('AGENT')||!defined('TOKEN')){
 tgSend($token,$adminChatId,"NexusGGR credentials belum di-set di config.php");
 http_response_code(200);echo'ok';exit;
 }
 $payload=json_encode(['method'=>'money_info','agent_code'=>AGENT,'agent_token'=>TOKEN]);
 $ch=curl_init(NEXUS_URL);
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
 CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
 CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
 $resp=curl_exec($ch);curl_close($ch);
 $d=json_decode($resp,true);
 if(!$d||$d['status']!=1){
 tgSend($token,$adminChatId,"Gagal cek agent:".($d['msg']??'error'));
 }else{
 $r="<b>AGENT NEXUSGGR</b>\n---------------\n";
 $r.="Code: <code>".htmlspecialchars($d['agent']['agent_code'])."</code>\n";
 $r.="Saldo: <b>".idr($d['agent']['balance'])."</b>\n";
 tgSend($token,$adminChatId,$r);
 }
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /game [username] — cek saldo user di game server (NexusGGR)
 if(preg_match('/^\/game\s+(\S+)/i',$t,$m)){
 $un=trim($m[1]);
 try{
 if(!defined('NEXUS_URL')||!defined('AGENT')||!defined('TOKEN')){
 tgSend($token,$adminChatId,"NexusGGR credentials belum di-set");
 http_response_code(200);echo'ok';exit;
 }
 // Cek user exist di DB
 $q=$db->prepare("SELECT id,username,balance FROM users WHERE username=? LIMIT 1");$q->execute([$un]);$u=$q->fetch();
 if(!$u){tgSend($token,$adminChatId,"User <code>$un</code> tidak ada di DB.");http_response_code(200);echo'ok';exit;}
 // Query NexusGGR
 $payload=json_encode(['method'=>'money_info','agent_code'=>AGENT,'agent_token'=>TOKEN,'user_code'=>$un]);
 $ch=curl_init(NEXUS_URL);
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
 CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
 CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
 $resp=curl_exec($ch);curl_close($ch);
 $d=json_decode($resp,true);
 $r="<b>GAME SERVER: $un</b>\n---------------\n";
 $r.="Saldo di Web: <b>".idr($u['balance'])."</b>\n";
 if($d&&$d['status']==1&&isset($d['user'])){
 $gameBal=intval($d['user']['balance']);
 $r.="Saldo di Game: <b>".idr($gameBal)."</b>\n";
 if($gameBal>0){
 $r.="\n User punya saldo di game server.\n";
 $r.="Withdraw reset dulu: <code>/saldo $un +$gameBal</code>";
 }else{
 $r.="\n Tidak ada saldo nyangkut di game.";
 }
 }else{
 $r.="User belum pernah masuk game / tidak terdaftar di NexusGGR.\n";
 $r.="<i>".htmlspecialchars($d['msg']??'unknown')."</i>";
 }
 tgSend($token,$adminChatId,$r);
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }

 // /chat [SID] — full diagnostic user yg lagi chat (saldo web+game, last deposit, riwayat)
 if(preg_match('/^\/chat\s+(\d+)/i',$t,$m)){
 $sid=intval($m[1]);
 try{
 // Get session + user
 $sq=$db->prepare("SELECT s.*,u.id as uid,u.username,u.phone,u.balance,u.vip_level,u.total_deposit,u.total_turnover,u.created_at as reg_at,u.status FROM chat_sessions s LEFT JOIN users u ON u.id=s.user_id WHERE s.id=? LIMIT 1");
 $sq->execute([$sid]);$ses=$sq->fetch();
 if(!$ses){tgSend($token,$adminChatId,"Sesi #$sid tidak ditemukan.");http_response_code(200);echo'ok';exit;}
 if(!$ses['uid']){tgSend($token,$adminChatId,"Sesi #$sid milik tamu (belum login) — username:".htmlspecialchars($ses['username']??'?'));http_response_code(200);echo'ok';exit;}

 $un=$ses['username'];$uid=$ses['uid'];
 $r="<b>DIAGNOSTIC CHAT #$sid</b>\n";
 $r.="---------------\n";
 $r.="<b>".htmlspecialchars($un)."</b> (ID:$uid)\n";
 if($ses['phone'])$r.="".htmlspecialchars($ses['phone'])."\n";
 $r.="VIP".intval($ses['vip_level'])."• Status:".($ses['status']??'active')."\n";
 $r.="Daftar:".date('d M Y',strtotime($ses['reg_at']))."\n\n";

 // ═══ 1. Saldo di Web ═══
 $webBal=intval($ses['balance']);
 $r.="<b>SALDO WEB:</b>".idr($webBal)."\n";

 // ═══ 2. Saldo di Game Server (NexusGGR) ═══
 $gameBal=null;$gameErr='';
 if(defined('NEXUS_URL')&&defined('AGENT')&&defined('TOKEN')){
 try{
 $payload=json_encode(['method'=>'money_info','agent_code'=>AGENT,'agent_token'=>TOKEN,'user_code'=>$un]);
 $ch=curl_init(NEXUS_URL);
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
 CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
 CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8]);
 $resp=curl_exec($ch);curl_close($ch);
 $d=json_decode($resp,true);
 if($d&&$d['status']==1&&isset($d['user'])){
 $gameBal=intval($d['user']['balance']);
 }else{
 $gameErr=$d['msg']??'user belum pernah login ke game';
 }
 }catch(Exception $e){$gameErr=$e->getMessage();}
 }else{
 $gameErr='NexusGGR belum di-set';
 }

 if($gameBal!==null){
 $r.="<b>SALDO GAME:</b>".idr($gameBal)."\n";
 $total=$webBal+$gameBal;
 $r.="<b>TOTAL:</b>".idr($total)."\n\n";
 if($gameBal>0){
 $r.="<b>Saldo NYANGKUT di game</b>".idr($gameBal).".\n";
 $r.="User main di Nexus tapi belum WD balik ke web.\n";
 $r.="Kalau user komplain saldo hilang, cek: dia udh main di slot/casino belum?\n";
 $r.="<code>/gamelog $un</code> — cek riwayat main di game\n\n";
 }else{
 $r.="Saldo game = 0. Bersih.\n\n";
 }
 }else{
 $r.="Saldo Game: <i>".htmlspecialchars($gameErr)."</i>\n\n";
 }

 // ═══ 3. Deposit Terakhir ═══
 $lastDep=$db->prepare("SELECT id,nominal,pay_amount,method,status,note,created_at,paid_at,tx_id FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
 $lastDep->execute([$uid]);$deps=$lastDep->fetchAll();
 if(empty($deps)){
 $r.="<b>DEPOSIT:</b> Belum pernah deposit.\n\n";
 }else{
 $r.="<b>5 DEPOSIT TERAKHIR:</b>\n";
 foreach($deps as $dep){
 $st=$dep['status'];
 $ic=$st==='paid'?'':($st==='pending'?'⏳':($st==='rejected'?'':($st==='expired'?'⌛':'')));
 $r.="$ic #".$dep['id']."".idr($dep['nominal']);
 if($dep['pay_amount']&&$dep['pay_amount']!=$dep['nominal'])$r.="(bayar".idr($dep['pay_amount']).")";
 $r.="•".$st."\n";
 $r.="".htmlspecialchars($dep['method']??'qris')."•".date('d/m H:i',strtotime($dep['created_at']));
 if($st==='paid'&&$dep['paid_at'])$r.="→ paid".date('d/m H:i',strtotime($dep['paid_at']));
 $r.="\n";
 if($dep['tx_id'])$r.="<code>".htmlspecialchars($dep['tx_id'])."</code>\n";
 }

 // Summary
 $sum=$db->prepare("SELECT COUNT(*) tot,COALESCE(SUM(CASE WHEN status='paid'THEN nominal END),0) paid_sum,COUNT(CASE WHEN status='pending'THEN 1 END) pnd,COUNT(CASE WHEN status='expired'THEN 1 END) exp,COUNT(CASE WHEN status='rejected'THEN 1 END) rej FROM deposits WHERE user_id=?");
 $sum->execute([$uid]);$s=$sum->fetch();
 $r.="\n Total".$s['tot']."tx • paid:".idr($s['paid_sum']);
 if($s['pnd']>0)$r.="• pending".$s['pnd'];
 if($s['exp']>0)$r.="• expired".$s['exp'];
 if($s['rej']>0)$r.="• rejected".$s['rej'];
 $r.="\n\n";

 // Kalau deposit terakhir pending/expired, kasih advice
 $last=$deps[0];
 if($last['status']==='pending'){
 $ageMin=round((time()-strtotime($last['created_at']))/60);
 $r.="⏳ <b>Deposit terakhir masih PENDING</b> ($ageMin menit)\n";
 if($ageMin<10)$r.="User mungkin lagi transfer. Tunggu dulu.\n";
 elseif($ageMin<60)$r.="Cek ke SquadOnyx panel, mungkin callback gagal.\n";
 else $r.="Sudah lama pending — kemungkinan user batal bayar. Bisa di-/reject\n";
 $r.="<code>/approve".$last['id']."</code> • <code>/reject".$last['id']."alasan</code>\n";
 }elseif($last['status']==='expired'){
 $r.="⌛ <b>Deposit terakhir EXPIRED</b>\n";
 $r.="User tidak bayar dalam waktu limit. Kalau sebenernya udah transfer, minta screenshot bukti trf + cek rekening tujuan manual.\n";
 }elseif($last['status']==='paid'){
 $mins=round((time()-strtotime($last['paid_at']??$last['created_at']))/60);
 $r.="Deposit terakhir <b>PAID</b> $mins menit lalu.\n";
 $r.="Kalau user bilang saldo tidak masuk, saldonya mungkin udah kepake main di game.\n";
 if($gameBal!==null&&$gameBal>0)$r.="Saldo di game sekarang:".idr($gameBal).".\n";
 }
 }

 // ═══ 4. Actions ═══
 $r.="\n <b>QUICK ACTIONS:</b>\n";
 $r.="<code>/history $un</code> — riwayat deposit lengkap\n";
 $r.="<code>/game $un</code> — detail saldo game\n";
 $r.="<code>/saldo $un +50000</code> — kompensasi saldo\n";
 $r.="<code>/r $sid pesan</code> — balas chat";

 tgSend($token,$adminChatId,$r);
 }catch(Exception $e){tgSend($token,$adminChatId,"Error:".$e->getMessage());}
 http_response_code(200);echo'ok';exit;
 }
 $sess=null;
 if(preg_match('/^\/r\s+(\d+)\s+(.+)/is',$t,$m)){
 $sq=$db->prepare("SELECT * FROM chat_sessions WHERE id=? LIMIT 1");
 $sq->execute([$m[1]]);$sess=$sq->fetch();
 $text=trim($m[2]);
 }

 // ═══════════════════════════════════════════════
 // Kalau bukan command: coba resolve session via reply_to_message_id
 // Pakai mapping table (chat_tg_map) yang menyimpan SEMUA tg_msg_id per sesi
 // ═══════════════════════════════════════════════
 if(!$sess&&$replyToId){
 // 1. Cek mapping table dulu (reliable, menyimpan semua pesan yg dikirim bot)
 $sq=$db->prepare("SELECT s.* FROM chat_sessions s JOIN chat_tg_map m ON m.session_id=s.id WHERE m.tg_msg_id=? LIMIT 1");
 $sq->execute([$replyToId]);$sess=$sq->fetch();
 // 2. Fallback: legacy tg_msg_id di chat_sessions
 if(!$sess){
 $sq=$db->prepare("SELECT * FROM chat_sessions WHERE tg_msg_id=? LIMIT 1");
 $sq->execute([$replyToId]);$sess=$sq->fetch();
 }
 }

 if(!$sess){
 // Legacy parse: [SID:123] in text
 if(preg_match('/\[SID:(\d+)\]/',$text,$m)){
 $sq=$db->prepare("SELECT * FROM chat_sessions WHERE id=? LIMIT 1");
 $sq->execute([$m[1]]);$sess=$sq->fetch();
 $text=preg_replace('/\[SID:\d+\]\s*/','',$text);
 }
 }
 
 if($sess){
 $msgType=$photo?'image':'text';
 $db->prepare("INSERT INTO chat_messages(session_id,sender,msg_type,content,file_url,tg_msg_id) VALUES(?,?,?,?,?,?)")
 ->execute([$sess['id'],'admin',$msgType,parseMsg($text),$photo,$msg['message_id']??null]);
 $db->prepare("UPDATE chat_sessions SET last_at=NOW() WHERE id=?")->execute([$sess['id']]);

 // Learn Q&A
 if($text){
 $lastUser=$db->prepare("SELECT content FROM chat_messages WHERE session_id=? AND sender='user'ORDER BY id DESC LIMIT 1");
 $lastUser->execute([$sess['id']]);$q=$lastUser->fetchColumn();
 if($q&&strlen($q)>5){
 $existing=$db->prepare("SELECT id,answer,hit_count FROM chat_autoreplies WHERE question=? LIMIT 1");
 $existing->execute([$q]);$ex=$existing->fetch();
 if($ex){
 if($ex['answer']===$text){
 $db->prepare("UPDATE chat_autoreplies SET hit_count=hit_count+1 WHERE id=?")->execute([$ex['id']]);
 }else{
 $db->prepare("INSERT INTO chat_autoreplies(question,answer,hit_count) VALUES(?,?,1)")->execute([$q,$text]);
 }
 }else{
 $db->prepare("INSERT INTO chat_autoreplies(question,answer,hit_count) VALUES(?,?,1)")->execute([$q,$text]);
 }
 }
 }

 try{$db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender='user'AND is_read=0")->execute([$sess['id']]);}catch(Exception $e){}

 // Log sukses buat debug
 @file_put_contents(__DIR__.'/../tg_webhook.log',date('Y-m-d H:i:s')."REPLY OK → sesi=".$sess['id']."user=".($sess['username']??'').(($text)?"text":($photo?"photo":""))."\n",FILE_APPEND);

 // Confirm reply — kasih info lengkap
 $preview=$text?mb_substr($text,0,40).(mb_strlen($text)>40?'...':''):'[foto]';
 $preview=htmlspecialchars($preview);
 $confirmMsg="Terkirim ke <b>".htmlspecialchars($sess['username']??'user')."</b> (sesi #".$sess['id'].")\n<i>\"$preview\"</i>";
 tgSend($token,$adminChatId,$confirmMsg,$msg['message_id']??null);
 }else{
 if($text||$photo){
 // Log debug — kenapa gagal find session
 @file_put_contents(__DIR__.'/../tg_webhook.log',date('Y-m-d H:i:s')."REPLY FAIL → replyToId=".($replyToId?:'null')."text=".substr($text,0,50)."\n",FILE_APPEND);
 // Kalau admin reply pesan tapi ga ketemu sesi, kasih petunjuk yg jelas
 if($replyToId){
 tgSend($token,$adminChatId,"<b>Pesan tidak ditemukan di database.</b>\n\nKemungkinan:\n• Sesi sudah ditutup\n• Bot baru di-reset / chat_tg_map kosong\n• Pesan yg di-reply terlalu lama\n\nCoba ketik <code>/list</code> untuk lihat sesi aktif, atau balas lewat pesan user yg terbaru.",$msg['message_id']??null);
 }else{
 tgSend($token,$adminChatId,"Pesan tidak dikenali.\n\n<b>Cara balas chat:</b>\n• <b>Reply</b> pesan user dari bot\n• Ketik <code>/help</code> untuk daftar perintah");
 }
 }
 }
 http_response_code(200);echo'ok';exit;
}

// ─── SEND MESSAGE (from user) ───
if($action==='send'){
 $uid=getUid();
 if(!$uid)err('Login dulu untuk pakai live chat',401);
 $text=trim((json_decode(file_get_contents('php://input'),true)['text']??$_POST['text']??''));
 if(!$text)err('Pesan kosong');
 
 $settings=getBotSettings($db);
 $sess=getSession($db,$uid,'',$settings);
 if(!$sess)err('Session error');
 
 // Save user message — simpan RAW, escape hanya saat render (web + TG)
 $db->prepare("INSERT INTO chat_messages(session_id,sender,msg_type,content) VALUES(?,?,?,?)")
 ->execute([$sess['id'],'user','text',$text]);
 $msgId=$db->lastInsertId();

 // ═══ CEK AUTO-REPLY DULU (sebelum forward ke Telegram) ═══
 $autoReply=null;$autoReplySource='';$detectedTopic=null;
 // 1. Welcome (pesan pertama di sesi ini)
 $msgCountQ=$db->prepare("SELECT COUNT(*) FROM chat_messages WHERE session_id=? AND sender='user'");
 $msgCountQ->execute([$sess['id']]);
 $userMsgCount=intval($msgCountQ->fetchColumn());
 if($userMsgCount==1&&!empty($settings['bot_welcome'])){
 $autoReply=parseMsg($settings['bot_welcome']);
 $autoReplySource='welcome';
 }
 // 2. Topic detection (keluhan umum: depo gagal, game error, cara depo, dll)
 if(!$autoReply){
 $detectedTopic=detectTopic($text);
 if($detectedTopic){
 // Update counter topic — kalo udah pernah, naikin escalation level
 try{
 $db->prepare("INSERT INTO chat_topic_count(session_id,topic,cnt) VALUES(?,?,1)
 ON DUPLICATE KEY UPDATE cnt=cnt+1, last_at=NOW()")
 ->execute([$sess['id'],$detectedTopic]);
 $cq=$db->prepare("SELECT cnt FROM chat_topic_count WHERE session_id=? AND topic=?");
 $cq->execute([$sess['id'],$detectedTopic]);
 $topicCount=intval($cq->fetchColumn()?:1);
 }catch(Exception $e){$topicCount=1;}

 // Khusus depo_gagal: pake data-aware reply (ambil riwayat depo real user)
 if($detectedTopic==='depo_gagal'&& $sess['user_id']){
 $tplReply=getDepoGagalReplyWithData($db,$sess['user_id'],$topicCount);
 }else{
 $tplReply=getTemplateReply($detectedTopic,$topicCount);
 }
 if($tplReply){
 $autoReply=$tplReply;
 $autoReplySource='topic_'.$detectedTopic.'_'.$topicCount;
 }

 // AUTO-BAN: kalau user spam keluhan tanpa dasar (count >= 6 + ga punya deposit valid)
 if($detectedTopic==='depo_gagal'&& $topicCount>=6 && $sess['user_id']){
 if(autoBanIfSpamming($db,$sess['user_id'],$sess['id'],$detectedTopic,$topicCount)){
 $autoReply.="\n\n---------------\n <b>AKUN ANDA TELAH DI-BANNED OTOMATIS</b>\n\nKarena spam keluhan tanpa dasar (".$topicCount."x). Hubungi CS jika merasa keberatan.";
 $autoReplySource.='_BANNED';
 // Auto-close session juga
 try{$db->prepare("UPDATE chat_sessions SET status='closed'WHERE id=?")->execute([$sess['id']]);}catch(Exception $e){}
 }
 }
 }
 }
 // 3. Smart reply (learned, fallback kalau topic ga kena)
 if(!$autoReply&&($settings['bot_smart_reply']??'1')==='1'){
 $ars=$db->query("SELECT * FROM chat_autoreplies ORDER BY hit_count DESC LIMIT 50")->fetchAll();
 $best=null;$bestScore=0;
 foreach($ars as $ar){
 similar_text(strtolower($text),strtolower($ar['question']),$pct);
 if($pct>70&&$pct>$bestScore){$bestScore=$pct;$best=$ar;}
 }
 if($best){$autoReply=$best['answer'];$autoReplySource='smart';}
 }

 // Simpan auto-reply ke DB (user akan terima via poll)
 if($autoReply){
 $db->prepare("INSERT INTO chat_messages(session_id,sender,msg_type,content) VALUES(?,?,?,?)")
 ->execute([$sess['id'],'admin','text',$autoReply]);
 }
 
 // ═══ Forward ke Telegram (pakai info auto-reply kalau ada) ═══
 $token=$settings['bot_token']??'';$adminId=$settings['bot_admin_chat_id']??'';
 if($token&&$adminId){
 // Ambil jumlah pesan di sesi ini
 $mcQ=$db->prepare("SELECT COUNT(*) FROM chat_messages WHERE session_id=?");
 $mcQ->execute([$sess['id']]);
 $mCount=intval($mcQ->fetchColumn());
 $isFirstMsg=($userMsgCount==1);

 // Fetch user context
 $ctx=getUserContext($db,$sess['user_id']);
 $uname=htmlspecialchars($sess['username']);
 $sid=$sess['id'];

 // Build inline keyboard (link ke admin panel)
 $domain=$_SERVER['HTTP_HOST']??'';
 $keyboard=null;
 if($domain){
 $keyboard=[
 [['text'=>'Lihat Riwayat','url'=>"https://$domain/team/chat_view.php?sid=$sid"]],
 ];
 }

 // Kirim header SESI BARU sekali saat pesan pertama
 $hdrMsgId=null;
 if($isFirstMsg){
 $hdr ="<b>SESI CHAT BARU</b>\n";
 $hdr.="--------------------\n";
 if($ctx){
 $dispId=$ctx['display_id']??('ID-'.intval($ctx['id']));
 $hdr.="<b>Sesi      :</b> #$sid\n";
 $hdr.="<b>ID User   :</b> <code>".htmlspecialchars($dispId)."</code>\n";
 $hdr.="<b>Username  :</b> <code>".htmlspecialchars($ctx['username'])."</code>\n";
 if(!empty($ctx['phone'])){
 $hdr.="<b>No. HP    :</b> <code>".htmlspecialchars($ctx['phone'])."</code>\n";
 }
 $hdr.="<b>VIP Level :</b> ".intval($ctx['vip_level'])."\n";
 $hdr.="<b>Saldo     :</b> ".idr($ctx['balance'])."\n";
 $hdr.="<b>Total Depo:</b> ".idr($ctx['total_deposit'])."\n";
 $hdr.="<b>Total Game:</b> ".idr($ctx['total_turnover'])."\n";
 $hdr.="<b>Tgl Daftar:</b> ".date('d M Y',strtotime($ctx['created_at']))."\n";
 }else{
 $hdr.="<b>Sesi :</b> #$sid\n";
 $hdr.="<b>User :</b> ".htmlspecialchars($uname)." (Tamu — belum login)\n";
 }
 $hdr.="--------------------\n";
 $hdr.="<b>Cara Balas:</b>\n";
 $hdr.="• Reply pesan di bawah → langsung kirim\n";
 $hdr.="• Atau ketik: <code>/r $sid pesan</code>\n";
 $hdr.="Diagnostic: <code>/chat $sid</code>";
 $hdrMsgId=tgSend($token,$adminId,$hdr,null,$keyboard);
 }

 // Pesan forward — RAPI PERBARIS, info user lengkap
 $fwd ="<b>PESAN BARU</b>\n";
 $fwd.="--------------------\n";
 if($ctx){
 $dispId=$ctx['display_id']??('ID-'.intval($ctx['id']));
 $fwd.="<b>Sesi      :</b> #$sid\n";
 $fwd.="<b>ID User   :</b> <code>".htmlspecialchars($dispId)."</code>\n";
 $fwd.="<b>Username  :</b> <code>".htmlspecialchars($ctx['username'])."</code>\n";
 if(!empty($ctx['phone'])){
 $fwd.="<b>No. HP    :</b> <code>".htmlspecialchars($ctx['phone'])."</code>\n";
 }
 $fwd.="<b>VIP Level :</b> ".intval($ctx['vip_level'])."\n";
 $fwd.="<b>Saldo     :</b> ".idr($ctx['balance'])."\n";
 $fwd.="<b>Total Depo:</b> ".idr($ctx['total_deposit'])."\n";
 $fwd.="<b>Total Game:</b> ".idr($ctx['total_turnover'])."\n";
 $fwd.="<b>Pesan ke  :</b> $userMsgCount\n";
 }else{
 $fwd.="<b>Sesi :</b> #$sid\n";
 $fwd.="<b>User :</b> ".htmlspecialchars($uname)." (Tamu)\n";
 $fwd.="<b>Pesan ke:</b> $userMsgCount\n";
 }
 $fwd.="--------------------\n";
 $fwd.="<b>Pesan:</b>\n";
 $fwd.=htmlspecialchars($text)."\n";
 $fwd.="--------------------\n";

 // Info auto-reply (kalau ada)
 if($autoReply){
 $label=$autoReplySource==='welcome'?'Welcome Message':'Smart Auto-Reply';
 $plainReply=trim(html_entity_decode(strip_tags($autoReply)));
 if(strlen($plainReply)>300)$plainReply=substr($plainReply,0,300).'...';
 $fwd.="<b>SUDAH DIBALAS OTOMATIS</b>\n";
 $fwd.="<b>Sumber:</b> $label\n";
 $fwd.="<b>Balasan:</b>\n";
 $fwd.="<i>".htmlspecialchars($plainReply)."</i>\n";
 $fwd.="--------------------\n";
 $fwd.="<i>↩ Reply pesan ini kalau mau tambahan jawaban</i>";
 }else{
 $fwd.="<i>↩ Reply pesan ini untuk balas\n";
 $fwd.="atau ketik: <code>/r $sid pesan</code></i>";
 }

 $tgMsgId=tgSend($token,$adminId,$fwd,null,$keyboard);
 // Simpan mapping tg_msg_id → session_id di table mapping (supaya admin reply pesan MANA SAJA tetep ketemu sesi)
 if($tgMsgId){
 try{$db->prepare("INSERT IGNORE INTO chat_tg_map(tg_msg_id,session_id) VALUES(?,?)")->execute([$tgMsgId,$sess['id']]);}catch(Exception $e){}
 // Juga update legacy field di chat_sessions (untuk backward compat)
 $db->prepare("UPDATE chat_sessions SET tg_msg_id=? WHERE id=?")->execute([$tgMsgId,$sess['id']]);
 }
 // Kalau pesan pertama, simpan juga tg_msg_id header-nya
 if($isFirstMsg&&!empty($hdrMsgId)){
 try{$db->prepare("INSERT IGNORE INTO chat_tg_map(tg_msg_id,session_id) VALUES(?,?)")->execute([$hdrMsgId,$sess['id']]);}catch(Exception $e){}
 }
 }

 ok(['msg_id'=>$msgId,'session_id'=>$sess['id']]);
}

// ─── POLL (get new messages) ───
if($action==='poll'){
 $uid=getUid();
 if(!$uid)err('Login dulu untuk pakai live chat',401);
 $d=json_decode(file_get_contents('php://input'),true)??[];
 $lastId=intval($d['last_id']??0);
 
 $s=$db->prepare("SELECT * FROM chat_sessions WHERE user_id=? AND status='open'ORDER BY id DESC LIMIT 1");
 $s->execute([$uid]);$sess=$s->fetch();
 
 if(!$sess){ok(['messages'=>[],'session_id'=>null]);exit;}
 
 $msgs=$db->prepare("SELECT id,sender,msg_type,content,file_url,created_at FROM chat_messages WHERE session_id=? AND id>? ORDER BY id ASC");
 $msgs->execute([$sess['id'],$lastId]);$list=$msgs->fetchAll();
 
 // Mark admin messages as read
 $db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender='admin'AND is_read=0")->execute([$sess['id']]);
 
 ok(['messages'=>$list,'session_id'=>$sess['id']]);
}

// ─── LOAD HISTORY ───
if($action==='history'){
 $uid=getUid();
 if(!$uid)err('Login dulu untuk pakai live chat',401);
 $s=$db->prepare("SELECT * FROM chat_sessions WHERE user_id=? AND status='open'ORDER BY id DESC LIMIT 1");
 $s->execute([$uid]);$sess=$s->fetch();
 if(!$sess){ok(['messages'=>[],'session_id'=>null]);exit;}
 $msgs=$db->query("SELECT id,sender,msg_type,content,file_url,created_at FROM chat_messages WHERE session_id={$sess['id']} ORDER BY id ASC LIMIT 100")->fetchAll();
 ok(['messages'=>$msgs,'session_id'=>$sess['id']]);
}

// ─── USER: upload gambar ke chat (dari cs_chat.php) ───
if($action==='user_chat_upload'){
 $uid=getUid();
 if(!$uid)err('Login dulu untuk pakai live chat',401);
 if(!isset($_FILES['file'])){echo json_encode(['ok'=>false,'error'=>'No file']);exit;}
 $f=$_FILES['file'];
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
 $allowed=['jpg','jpeg','png','gif','webp'];
 if(!in_array($ext,$allowed)){echo json_encode(['ok'=>false,'error'=>'Format tidak didukung']);exit;}
 if($f['size']>10*1024*1024){echo json_encode(['ok'=>false,'error'=>'Maksimal 10MB']);exit;}
 $dir=realpath(__DIR__.'/../asset').'/uploads/chat/';
 if(!is_dir($dir))@mkdir($dir,0755,true);
 if(!is_dir($dir)){echo json_encode(['ok'=>false,'error'=>'Gagal buat folder chat']);exit;}
 $name='u_'.date('Ymd').'_'.time().'_'.rand(1000,9999).'.'.$ext;
 $dest=$dir.$name;
 if(!move_uploaded_file($f['tmp_name'],$dest)){echo json_encode(['ok'=>false,'error'=>'Upload gagal']);exit;}
 $url='asset/uploads/chat/'.$name;
 $scheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
 $host=$_SERVER['HTTP_HOST']??'';
 $fullUrl=$host?"$scheme://$host/$url":$url;

 // Simpan ke chat + forward ke telegram
 $settings=getBotSettings($db);
 $sess=getSession($db,$uid,'',$settings);
 if($sess){
 $caption=trim($_POST['text']??'');
 // Simpan RAW ke DB (escape hanya saat render)
 $db->prepare("INSERT INTO chat_messages(session_id,sender,msg_type,content,file_url) VALUES(?,?,?,?,?)")
 ->execute([$sess['id'],'user','image',$caption?:null,$fullUrl]);
 // Forward ke TG (sendPhoto)
 $token=$settings['bot_token']??'';$adminId=$settings['bot_admin_chat_id']??'';
 if($token&&$adminId){
 $ctx=getUserContext($db,$sess['user_id']);
 $uname=htmlspecialchars($sess['username']);
 $sid=$sess['id'];
 // Escape caption saat render TG (bukan saat simpan)
 $fwdCap="<b>#$sid</b> •".($ctx?htmlspecialchars($ctx['username']):$uname)."\n".($caption?htmlspecialchars($caption):"[Foto dari user]")."\n<i>↩ Reply untuk balas | /r $sid ...</i>";
 $ch=curl_init("https://api.telegram.org/bot$token/sendPhoto");
 curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode([
'chat_id'=>$adminId,
'photo'=>$fullUrl,
'caption'=>$fwdCap,
'parse_mode'=>'HTML'
 ]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
 $res=json_decode(curl_exec($ch),true);curl_close($ch);
 $tgId=$res['result']['message_id']??null;
 if($tgId){
 try{$db->prepare("INSERT IGNORE INTO chat_tg_map(tg_msg_id,session_id) VALUES(?,?)")->execute([$tgId,$sess['id']]);}catch(Exception $e){}
 $db->prepare("UPDATE chat_sessions SET tg_msg_id=? WHERE id=?")->execute([$tgId,$sess['id']]);
 }
 }
 }
 echo json_encode(['ok'=>true,'url'=>$url,'full_url'=>$fullUrl,'session_id'=>$sess['id']??null]);exit;
}

// ─── ADMIN: upload gambar untuk chat ───
if($action==='admin_chat_upload'){
 $uid=getUid();
 if(!$uid){echo json_encode(['ok'=>false,'error'=>'NOT_LOGGED_IN']);exit;}
 $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);$role=$u->fetchColumn();
 if($role!=='admin'){echo json_encode(['ok'=>false,'error'=>'FORBIDDEN']);exit;}
 if(!isset($_FILES['file'])){echo json_encode(['ok'=>false,'error'=>'No file']);exit;}
 $f=$_FILES['file'];
 $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
 $allowed=['jpg','jpeg','png','gif','webp'];
 if(!in_array($ext,$allowed)){echo json_encode(['ok'=>false,'error'=>'Format tidak didukung:'.$ext]);exit;}
 if($f['size']>10*1024*1024){echo json_encode(['ok'=>false,'error'=>'Maksimal 10MB']);exit;}
 $dir=realpath(__DIR__.'/../asset').'/uploads/chat/';
 if(!is_dir($dir))@mkdir($dir,0755,true);
 if(!is_dir($dir)){echo json_encode(['ok'=>false,'error'=>'Gagal buat folder chat']);exit;}
 $name=date('Ymd').'_'.time().'_'.rand(1000,9999).'.'.$ext;
 $dest=$dir.$name;
 if(!move_uploaded_file($f['tmp_name'],$dest)){echo json_encode(['ok'=>false,'error'=>'Upload gagal, cek permission asset/uploads/']);exit;}
 $url='asset/uploads/chat/'.$name;
 $scheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
 $host=$_SERVER['HTTP_HOST']??'';
 $fullUrl=$host?"$scheme://$host/$url":$url;
 echo json_encode(['ok'=>true,'url'=>$url,'full_url'=>$fullUrl]);exit;
}

// ─── ADMIN: kirim balasan dari admin panel web ───
if($action==='admin_reply'){
 $uid=getUid();
 if(!$uid)err('NOT_LOGGED_IN',401);
 $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);$role=$u->fetchColumn();
 if($role!=='admin')err('FORBIDDEN',403);
 $sid=intval($d['sid']??0);
 $text=trim($d['text']??'');
 $fileUrl=trim($d['file_url']??'');
 if(!$sid)err('SID wajib');
 if(!$text&&!$fileUrl)err('Pesan atau gambar wajib diisi');
 $sess=$db->prepare("SELECT * FROM chat_sessions WHERE id=?");$sess->execute([$sid]);$sess=$sess->fetch();
 if(!$sess)err('Sesi tidak ditemukan');
 // Simpan balasan admin
 $msgType=$fileUrl?'image':'text';
 $db->prepare("INSERT INTO chat_messages(session_id,sender,msg_type,content,file_url,is_read) VALUES(?,?,?,?,?,0)")
 ->execute([$sid,'admin',$msgType,parseMsg($text),$fileUrl?:null]);
 $db->prepare("UPDATE chat_sessions SET last_at=NOW() WHERE id=?")->execute([$sid]);
 // Mark user messages as read (karena admin sudah respons)
 $db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender='user'")->execute([$sid]);
 // Learn untuk smart reply (hanya kalau ada teks, bukan gambar)
 if($text&&!$fileUrl){
 $lastUser=$db->prepare("SELECT content FROM chat_messages WHERE session_id=? AND sender='user'ORDER BY id DESC LIMIT 1");
 $lastUser->execute([$sid]);$q=$lastUser->fetchColumn();
 if($q&&strlen($q)>5){
 $existing=$db->prepare("SELECT id,answer FROM chat_autoreplies WHERE question=? LIMIT 1");
 $existing->execute([$q]);$ex=$existing->fetch();
 if($ex){
 if($ex['answer']===$text)$db->prepare("UPDATE chat_autoreplies SET hit_count=hit_count+1 WHERE id=?")->execute([$ex['id']]);
 else $db->prepare("INSERT INTO chat_autoreplies(question,answer,hit_count) VALUES(?,?,1)")->execute([$q,$text]);
 }else{
 $db->prepare("INSERT INTO chat_autoreplies(question,answer,hit_count) VALUES(?,?,1)")->execute([$q,$text]);
 }
 }
 }
 ok(['msg_id'=>$db->lastInsertId()]);
}

// ─── ADMIN: poll pesan baru untuk sesi tertentu (untuk live refresh) ───
if($action==='admin_poll'){
 $uid=getUid();
 if(!$uid)err('NOT_LOGGED_IN',401);
 $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);$role=$u->fetchColumn();
 if($role!=='admin')err('FORBIDDEN',403);
 $sid=intval($d['sid']??0);
 $lastId=intval($d['last_id']??0);
 if(!$sid)err('SID wajib');
 $msgs=$db->prepare("SELECT id,sender,msg_type,content,file_url,created_at FROM chat_messages WHERE session_id=? AND id>? ORDER BY id ASC");
 $msgs->execute([$sid,$lastId]);
 ok(['messages'=>$msgs->fetchAll()]);
}

// ─── ADMIN: tutup sesi ───
if($action==='admin_close_session'){
 $uid=getUid();
 if(!$uid)err('NOT_LOGGED_IN',401);
 $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);$role=$u->fetchColumn();
 if($role!=='admin')err('FORBIDDEN',403);
 $sid=intval($d['sid']??0);
 if(!$sid)err('SID wajib');
 $db->prepare("UPDATE chat_sessions SET status='closed'WHERE id=?")->execute([$sid]);
 ok();
}

err('INVALID_ACTION');
