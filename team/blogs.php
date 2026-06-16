<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Blog');
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <p style="font-size:.72rem;color:var(--t3)">Postingan blog ditampilkan di halaman depan</p>
  <button class="btn btn-pri" onclick="openForm()">+ Tulis Post</button>
</div>
<div id="blogList"></div>
<div id="statusMsg"></div>

<!-- Form - full page overlay -->
<div id="formModal" style="display:none;position:fixed;inset:0;background:var(--bg);z-index:200;overflow-y:auto;padding:20px">
<div style="max-width:700px;margin:0 auto">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px">
    <h3 id="formTitle" style="font-size:.95rem;font-weight:700">Tulis Post Blog</h3>
    <button class="btn btn-sec" onclick="closeForm()">← Kembali</button>
  </div>
  <input type="hidden" id="fId">
  <div class="fg"><label>Judul Post</label><input id="fTitle" placeholder="Judul blog post..."></div>
  <div class="fg">
    <label>Isi Konten (HTML didukung)</label>
    <div style="display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px">
      <button class="btn btn-sec btn-sm" onclick="ins('<b>','</b>')"><b>B</b></button>
      <button class="btn btn-sec btn-sm" onclick="ins('<i>','</i>')"><i>I</i></button>
      <button class="btn btn-sec btn-sm" onclick="ins('<br>','')">BR</button>
      <button class="btn btn-sec btn-sm" onclick="ins('<img src=\\\"\\\" style=\\\"width:100%;border-radius:8px\\\">','')">IMG</button>
      <button class="btn btn-sec btn-sm" onclick="ins('<div style=\\\"background:#1a1a2e;border-radius:8px;padding:12px;margin:8px 0\\\">','</div>')">BOX</button>
      <button class="btn btn-sec btn-sm" onclick="ins('<span style=\\\"color:#4ade80;font-weight:700\\\">','</span>')">Green</button>
      <button class="btn btn-sec btn-sm" onclick="ins('<span style=\\\"color:#fbbf24;font-weight:700\\\">','</span>')">Gold</button>
    </div>
    <textarea id="fBody" rows="14" style="font-family:monospace;font-size:.75rem" placeholder="Isi konten blog..."></textarea>
  </div>
  <div style="background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:12px;margin-bottom:16px">
    <div style="font-size:.68rem;font-weight:700;color:var(--t3);margin-bottom:8px">PREVIEW</div>
    <div id="preview" style="font-size:.78rem;color:var(--t2);line-height:1.7"></div>
  </div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-pri" onclick="saveBlog()" style="flex:1;padding:12px">Publish</button>
    <button class="btn btn-sec" onclick="closeForm()" style="flex:1;padding:12px">Batal</button>
  </div>
</div>
</div>

<script>
var API='../api/admin.php';
var allBlogs=[];

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(()=>el.innerHTML='',3000)}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function ins(before,after){
  var ta=document.getElementById('fBody');
  var s=ta.selectionStart,e=ta.selectionEnd,v=ta.value;
  ta.value=v.slice(0,s)+before+v.slice(s,e)+after+v.slice(e);
  ta.selectionStart=s+before.length;ta.selectionEnd=s+before.length+(e-s);ta.focus();
  updatePreview();
}

document.addEventListener('DOMContentLoaded',()=>{
  document.getElementById('fBody').addEventListener('input',updatePreview);
});
function updatePreview(){document.getElementById('preview').innerHTML=document.getElementById('fBody').value}

function loadBlogs(){
  api({action:'get_blog_posts'}).then(d=>{
    if(!d.ok)return;
    allBlogs=d.blogs||[];
    var h='';
    if(!allBlogs.length){h='<div style="color:var(--t3);font-size:.8rem;padding:20px 0">Belum ada blog post.</div>';}
    allBlogs.forEach(b=>{
      h+='<div class="item-row" style="align-items:flex-start">';
      h+='<div class="ir-info"><div class="ir-title" style="font-size:.85rem">'+esc(b.title)+'</div>';
      h+='<div class="ir-sub">'+esc((b.body||'').replace(/<[^>]*>/g,'').substring(0,80))+'...</div>';
      h+='<div class="ir-sub" style="margin-top:2px">'+fmtDate(b.created_at)+'</div></div>';
      h+='<div class="ir-actions"><button class="btn btn-sec btn-sm" onclick="editBlog('+b.id+')">Edit</button>';
      h+='<button class="btn btn-red btn-sm" onclick="delBlog('+b.id+')">×</button></div></div>';
    });
    document.getElementById('blogList').innerHTML=h;
  });
}

function fmtDate(dt){if(!dt)return'-';return dt.substring(0,16).replace('T',' ');}

function openForm(b){
  document.getElementById('formTitle').textContent=b?'Edit Post':'Tulis Post Blog';
  document.getElementById('fId').value=b?b.id:'';
  document.getElementById('fTitle').value=b?b.title:'';
  document.getElementById('fBody').value=b?b.body:'';
  updatePreview();
  document.getElementById('formModal').style.display='block';
}
function editBlog(id){var b=allBlogs.find(x=>x.id==id);if(b)openForm(b);}
function closeForm(){document.getElementById('formModal').style.display='none'}

function saveBlog(){
  var title=document.getElementById('fTitle').value.trim();
  var body=document.getElementById('fBody').value.trim();
  if(!title)return msg('Judul wajib diisi',false);
  var payload={action:'save_blog',title,body};
  var id=document.getElementById('fId').value;if(id)payload.id=parseInt(id);
  api(payload).then(d=>{if(d.ok){closeForm();loadBlogs();msg('Dipublish!',true);}else msg(d.error||'Gagal',false)});
}

function delBlog(id){
  if(!confirm('Hapus post ini?'))return;
  api({action:'delete_blog',id}).then(d=>{if(d.ok){loadBlogs();msg('Dihapus!',true);}});
}

loadBlogs();
</script>
<?php adminFooter(); ?>
