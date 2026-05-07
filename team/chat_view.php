<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Live Chat');
$sid=intval($_GET['sid']??0);
if(!$sid){header('Location:chat_settings.php');exit;}
$sess=$db->prepare("SELECT s.*,u.phone,u.vip_level,u.balance,u.total_deposit,u.total_turnover,u.created_at as u_created FROM chat_sessions s LEFT JOIN users u ON u.id=s.user_id WHERE s.id=?");
$sess->execute([$sid]);$sess=$sess->fetch();
if(!$sess){header('Location:chat_settings.php');exit;}

// Mark all user messages as read when admin opens chat
$db->prepare("UPDATE chat_messages SET is_read=1 WHERE session_id=? AND sender='user' AND is_read=0")->execute([$sid]);

$msgs=$db->query("SELECT * FROM chat_messages WHERE session_id=$sid ORDER BY id ASC")->fetchAll();
$lastId=end($msgs)['id']??0;
$stats=[
    'total'=>count($msgs),
    'user'=>count(array_filter($msgs,fn($m)=>$m['sender']==='user')),
    'admin'=>count(array_filter($msgs,fn($m)=>$m['sender']==='admin')),
];
function fmtIDR($n){return 'Rp '.number_format(intval($n),0,',','.');}
?>
<style>
.msg-row{display:flex;flex-direction:column}
.msg-bubble{max-width:75%;padding:9px 12px;border-radius:12px;font-size:.75rem;line-height:1.5;word-wrap:break-word}
.msg-bubble.user{background:rgba(var(--sec-rgb,56,189,248),.2);border:1px solid rgba(var(--sec-rgb,56,189,248),.3);align-self:flex-end}
.msg-bubble.admin{background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.25);align-self:flex-start}
.msg-meta{font-size:.55rem;color:var(--t3);margin-top:2px}
.reply-form{display:flex;gap:8px;margin-top:12px;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:10px;align-items:flex-end}
.reply-form textarea{flex:1;background:var(--bg);border:1px solid var(--bd);color:var(--t);padding:10px 12px;border-radius:8px;font-size:.75rem;font-family:inherit;resize:vertical;min-height:38px;max-height:120px}
.reply-form textarea:focus{outline:none;border-color:var(--sec)}
.reply-form button{background:var(--sec);border:none;color:#fff;padding:0 18px;border-radius:8px;font-weight:700;font-size:.75rem;cursor:pointer;white-space:nowrap;font-family:inherit;height:38px}
.reply-form button:hover{background:#17a589}
.reply-form button:disabled{opacity:.5;cursor:not-allowed}
.attach-btn{background:var(--bg)!important;border:1px solid var(--bd)!important;color:var(--t2)!important;padding:0 12px!important;width:42px;display:flex;align-items:center;justify-content:center}
.attach-btn:hover{border-color:var(--sec)!important;color:var(--sec)!important}
.img-preview{margin-top:10px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:10px;display:flex;gap:10px;align-items:center}
.img-preview img{max-width:100px;max-height:100px;border-radius:6px;object-fit:cover}
.img-preview .pv-info{flex:1;font-size:.7rem;color:var(--t2)}
.img-preview .pv-cancel{background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);color:#ef4444;padding:5px 10px;border-radius:6px;font-size:.65rem;cursor:pointer;font-family:inherit}
.live-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#4ade80;margin-right:4px;animation:pulse 1.5s infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.3}}
</style>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap">
  <a href="chat_settings.php" class="btn btn-sec btn-sm">← Kembali</a>
  <h3 style="font-size:.9rem;margin:0">Sesi #<?=$sid?></h3>
  <span style="background:<?=$sess['status']==='open'?'rgba(74,222,128,.15)':'rgba(156,163,175,.15)'?>;color:<?=$sess['status']==='open'?'#4ade80':'#9ca3af'?>;font-size:.62rem;font-weight:700;padding:3px 9px;border-radius:6px;text-transform:uppercase"><?=$sess['status']?></span>
  <span style="font-size:.62rem;color:var(--t3)"><span class="live-dot"></span>Live</span>
  <?php if($sess['status']==='open'): ?>
  <button class="btn btn-red btn-sm" onclick="closeSession()" style="margin-left:auto">Tutup Sesi</button>
  <?php endif; ?>
</div>

<!-- USER INFO CARD -->
<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:14px;margin-bottom:14px">
  <div style="display:flex;gap:12px;align-items:flex-start">
    <div style="width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--sec,#38bdf8),var(--sec-d,#0ea5e9));display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;flex-shrink:0">
      <?=strtoupper(substr($sess['username'],0,1))?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-size:.82rem;font-weight:700"><?=htmlspecialchars($sess['username'])?></div>
      <div style="font-size:.62rem;color:var(--t3);margin-top:2px">
        <?php if($sess['user_id']): ?>
          ID: <?=$sess['user_id']?> • VIP <?=intval($sess['vip_level'])?> • <?=htmlspecialchars($sess['phone']?:'—')?>
        <?php else: ?>
          Tamu (belum login)
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php if($sess['user_id']): ?>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <div style="flex:1;min-width:110px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;padding:8px 10px">
      <div style="font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px">Saldo</div>
      <div style="font-size:.78rem;font-weight:700"><?=fmtIDR($sess['balance'])?></div>
    </div>
    <div style="flex:1;min-width:110px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;padding:8px 10px">
      <div style="font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px">Total Depo</div>
      <div style="font-size:.78rem;font-weight:700"><?=fmtIDR($sess['total_deposit'])?></div>
    </div>
    <div style="flex:1;min-width:110px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;padding:8px 10px">
      <div style="font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px">Turnover</div>
      <div style="font-size:.78rem;font-weight:700"><?=fmtIDR($sess['total_turnover'])?></div>
    </div>
  </div>
  <div style="font-size:.6rem;color:var(--t3);margin-top:8px">Daftar: <?=date('d M Y',strtotime($sess['u_created']))?> • Sesi dibuka: <?=substr($sess['created_at'],0,16)?></div>
  <?php endif; ?>
</div>

<!-- STATS -->
<div style="display:flex;gap:8px;margin-bottom:12px;font-size:.65rem" id="statsBar">
  <span style="background:var(--bg2);border:1px solid var(--bd);padding:4px 10px;border-radius:6px">Total: <b id="stTotal"><?=$stats['total']?></b></span>
  <span style="background:rgba(var(--sec-rgb,56,189,248),.1);border:1px solid rgba(var(--sec-rgb,56,189,248),.25);padding:4px 10px;border-radius:6px">User: <b id="stUser"><?=$stats['user']?></b></span>
  <span style="background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.2);padding:4px 10px;border-radius:6px">Admin: <b id="stAdmin"><?=$stats['admin']?></b></span>
</div>

<!-- MESSAGES -->
<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:14px;max-height:480px;overflow-y:auto;display:flex;flex-direction:column;gap:8px" id="msgBox">
<?php foreach($msgs as $m): $isUser=$m['sender']==='user'; ?>
<div class="msg-row" data-id="<?=$m['id']?>">
<div class="msg-bubble <?=$isUser?'user':'admin'?>">
<?php if($m['file_url']): ?><img src="<?=htmlspecialchars($m['file_url'])?>" style="max-width:180px;border-radius:8px;display:block;margin-bottom:4px"><?php endif; ?>
<?=nl2br($isUser?htmlspecialchars($m['content']??''):($m['content']??''))?>
</div>
<div class="msg-meta" style="align-self:<?=$isUser?'flex-end':'flex-start'?>"><?=$isUser?'👤 User':'🛠️ Admin'?> • <?=substr($m['created_at'],0,16)?></div>
</div>
<?php endforeach; ?>
</div>

<?php if($sess['status']==='open'): ?>
<!-- IMAGE PREVIEW (hidden until file selected) -->
<div class="img-preview" id="imgPreview" style="display:none">
  <img id="pvImg" src="" alt="">
  <div class="pv-info">
    <div id="pvName" style="font-weight:700;color:var(--t)"></div>
    <div id="pvSize" style="font-size:.6rem;color:var(--t3);margin-top:2px"></div>
  </div>
  <button class="pv-cancel" onclick="cancelImage()">× Batal</button>
</div>

<!-- REPLY FORM -->
<div class="reply-form">
  <input type="file" id="imgInput" accept="image/*" style="display:none" onchange="onImgPick(event)">
  <button class="attach-btn" onclick="document.getElementById('imgInput').click()" title="Lampirkan gambar">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
  </button>
  <textarea id="replyTxt" placeholder="Ketik balasan ke <?=htmlspecialchars($sess['username'])?>... (Shift+Enter baris baru, Enter kirim)" rows="1"></textarea>
  <button onclick="sendReply()" id="sendBtn">Kirim</button>
</div>
<div style="font-size:.6rem;color:var(--t3);margin-top:6px">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12" style="vertical-align:-1px"><path d="M12 2a7 7 0 017 7c0 2.5-1.5 4.5-3 5.5V17h-8v-2.5C6.5 13.5 5 11.5 5 9a7 7 0 017-7z"/></svg> Format link: <code style="background:rgba(255,255,255,.08);padding:1px 5px;border-radius:3px">#a teks|https://url</code> • Gambar max 10MB (jpg/png/webp/gif)
</div>
<?php else: ?>
<div style="background:rgba(156,163,175,.08);border:1px solid rgba(156,163,175,.2);border-radius:10px;padding:12px;text-align:center;font-size:.7rem;color:var(--t3)">
  Sesi ini sudah ditutup. Tidak bisa balas lagi.
</div>
<?php endif; ?>

<div style="background:rgba(var(--sec-rgb,56,189,248),.05);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:10px;padding:12px;font-size:.68rem;color:var(--t2);line-height:1.6;margin-top:10px">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12" style="vertical-align:-1px"><path d="M12 2a7 7 0 017 7c0 2.5-1.5 4.5-3 5.5V17h-8v-2.5C6.5 13.5 5 11.5 5 9a7 7 0 017-7z"/></svg> <b>Dua cara balas:</b> Langsung dari sini (atas) atau via Telegram bot — ketik <code style="background:rgba(255,255,255,.08);padding:1px 5px;border-radius:3px">/r <?=$sid?> pesan</code> di chat bot.
</div>

<script>
var SID=<?=$sid?>;
var lastId=<?=$lastId?>;
var API='../api/chat.php';
var STATUS='<?=$sess['status']?>';
var pendingFile=null;

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}

function esc(s){return (s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}

function appendMsg(m){
  var isUser=m.sender==='user';
  var row=document.createElement('div');
  row.className='msg-row';row.setAttribute('data-id',m.id);
  var content=isUser?esc(m.content||'').replace(/\n/g,'<br>'):(m.content||'').replace(/\n/g,'<br>');
  var img=m.file_url?'<img src="'+esc(m.file_url)+'" style="max-width:180px;border-radius:8px;display:block;margin-bottom:4px;cursor:pointer" onclick="window.open(this.src)">':'';
  row.innerHTML='<div class="msg-bubble '+(isUser?'user':'admin')+'">'+img+content+'</div>'+
    '<div class="msg-meta" style="align-self:'+(isUser?'flex-end':'flex-start')+'">'+(isUser?'👤 User':'🛠️ Admin')+' • '+(m.created_at||'').substring(0,16)+'</div>';
  document.getElementById('msgBox').appendChild(row);
  var stTotal=document.getElementById('stTotal');stTotal.textContent=parseInt(stTotal.textContent)+1;
  var stKey=isUser?'stUser':'stAdmin';var st=document.getElementById(stKey);st.textContent=parseInt(st.textContent)+1;
}

function scrollBottom(){var b=document.getElementById('msgBox');b.scrollTop=b.scrollHeight;}

function onImgPick(e){
  var f=e.target.files[0];
  if(!f)return;
  if(f.size>10*1024*1024){alert('File terlalu besar! Max 10MB');e.target.value='';return;}
  if(!/^image\//.test(f.type)){alert('Hanya file gambar yang diizinkan');e.target.value='';return;}
  pendingFile=f;
  // Show preview
  var fr=new FileReader();
  fr.onload=function(ev){document.getElementById('pvImg').src=ev.target.result;};
  fr.readAsDataURL(f);
  document.getElementById('pvName').textContent=f.name;
  document.getElementById('pvSize').textContent=(f.size/1024).toFixed(1)+' KB';
  document.getElementById('imgPreview').style.display='flex';
}

function cancelImage(){
  pendingFile=null;
  document.getElementById('imgInput').value='';
  document.getElementById('imgPreview').style.display='none';
}

function uploadImage(file){
  var fd=new FormData();
  fd.append('file',file);
  fd.append('action','admin_chat_upload');
  return fetch(API+'?action=admin_chat_upload',{method:'POST',credentials:'same-origin',body:fd}).then(r=>r.json());
}

function sendReply(){
  var t=document.getElementById('replyTxt').value.trim();
  if(!t&&!pendingFile)return;
  var btn=document.getElementById('sendBtn');btn.disabled=true;btn.textContent='...';

  var doSend=function(fileUrl){
    api({action:'admin_reply',sid:SID,text:t,file_url:fileUrl||''}).then(d=>{
      btn.disabled=false;btn.textContent='Kirim';
      if(d.ok){
        document.getElementById('replyTxt').value='';
        cancelImage();
        poll();
      }else{
        alert(d.error||'Gagal kirim');
      }
    }).catch(()=>{btn.disabled=false;btn.textContent='Kirim';alert('Network error')});
  };

  if(pendingFile){
    btn.textContent='Upload...';
    uploadImage(pendingFile).then(d=>{
      if(d.ok){
        doSend(d.url);
      }else{
        btn.disabled=false;btn.textContent='Kirim';
        alert(d.error||'Upload gagal');
      }
    }).catch(()=>{btn.disabled=false;btn.textContent='Kirim';alert('Upload error')});
  }else{
    doSend('');
  }
}

function poll(){
  if(STATUS!=='open')return;
  api({action:'admin_poll',sid:SID,last_id:lastId}).then(d=>{
    if(d.ok&&d.messages&&d.messages.length){
      d.messages.forEach(m=>{appendMsg(m);lastId=Math.max(lastId,m.id);});
      scrollBottom();
    }
  }).catch(()=>{});
}

function closeSession(){
  if(!confirm('Tutup sesi chat ini? User tidak bisa kirim pesan lagi di sesi yang sama.'))return;
  api({action:'admin_close_session',sid:SID}).then(d=>{
    if(d.ok)location.reload();
    else alert(d.error||'Gagal');
  });
}

// Enter to send (Shift+Enter = newline)
document.getElementById('replyTxt')?.addEventListener('keydown',function(e){
  if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendReply();}
});

// Paste image from clipboard
document.getElementById('replyTxt')?.addEventListener('paste',function(e){
  var items=(e.clipboardData||e.originalEvent.clipboardData).items;
  for(var i=0;i<items.length;i++){
    if(items[i].type.indexOf('image')===0){
      e.preventDefault();
      var f=items[i].getAsFile();
      if(f){
        pendingFile=f;
        var fr=new FileReader();
        fr.onload=function(ev){document.getElementById('pvImg').src=ev.target.result;};
        fr.readAsDataURL(f);
        document.getElementById('pvName').textContent='Gambar dari clipboard';
        document.getElementById('pvSize').textContent=(f.size/1024).toFixed(1)+' KB';
        document.getElementById('imgPreview').style.display='flex';
      }
      break;
    }
  }
});

// Auto-scroll & start polling
scrollBottom();
if(STATUS==='open')setInterval(poll,3000);
</script>
<?php adminFooter(); ?>
