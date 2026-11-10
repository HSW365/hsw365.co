(function(){
var ROOT=document.body.getAttribute('data-root')||'';
var KEY='hsw365_cart';
function load(){try{return JSON.parse(localStorage.getItem(KEY))||[]}catch(e){return window.__c||[]}}
function save(c){window.__c=c;try{localStorage.setItem(KEY,JSON.stringify(c))}catch(e){}count()}
function count(){var n=load().reduce(function(a,l){return a+l.q},0);var el=document.getElementById('cc');if(el)el.textContent=n}
function money(x){return '$'+(x%1?x.toFixed(2):x.toLocaleString())}
count();
var add=document.getElementById('add');
if(add){
  var sel=document.getElementById('var'),price=document.getElementById('price');
  sel.addEventListener('change',function(){price.textContent=money(parseFloat(sel.options[sel.selectedIndex].getAttribute('data-p')))});
  add.addEventListener('click',function(){
    var c=load(),h=add.getAttribute('data-h'),v=parseInt(sel.value,10);
    var f=c.filter(function(l){return l.h===h&&l.v===v})[0];
    if(f)f.q++;else c.push({h:h,v:v,q:1});
    save(c);add.textContent='Added - view cart';add.onclick=function(){location.href=ROOT+'cart.html'};
    add.addEventListener('click',function(){location.href=ROOT+'cart.html'},{once:true});
  });
  document.querySelectorAll('.th').forEach(function(t){t.addEventListener('click',function(){document.getElementById('mainimg').src=t.getAttribute('data-src')})});
}
var grid=document.getElementById('grid');
if(grid){
  var fb=document.querySelectorAll('#filters button');
  function setF(f){fb.forEach(function(b){b.classList.toggle('on',b.getAttribute('data-f')===f)});grid.querySelectorAll('.card').forEach(function(c){c.style.display=(f==='all'||c.getAttribute('data-g')===f)?'':'none'})}
  fb.forEach(function(b){b.addEventListener('click',function(){setF(b.getAttribute('data-f'))})});
  var g=new URLSearchParams(location.search).get('g');setF(g||'all');
}
var lines=document.getElementById('lines');
if(lines){
  fetch(ROOT+'assets/products.json').then(function(r){return r.json()}).then(function(P){
    function render(){
      var c=load().filter(function(l){return P[l.h]&&P[l.h].variants[l.v]});var sub=0,phys=false,h='';
      if(!c.length)h='<p class="mut">Your cart is empty. <a href="shop.html" style="color:var(--cy)">Shop now</a></p>';
      c.forEach(function(l,i){var p=P[l.h],v=p.variants[l.v];sub+=v.p*l.q;if(p.physical)phys=true;
        h+='<div class="line"><img src="'+p.img+'" alt=""><div><h4>'+p.title+'</h4><div class="mut small">'+(v.t==='Default Title'?'':v.t)+'</div><div class="q"><button data-i="'+i+'" data-d="-1">-</button>'+l.q+'<button data-i="'+i+'" data-d="1">+</button><button class="rm" data-i="'+i+'" data-d="x">Remove</button></div></div><b>'+money(v.p*l.q)+'</b></div>'});
      lines.innerHTML=h;document.getElementById('sub').textContent=money(sub);
      var a=document.getElementById('addr');a.style.display=phys?'':'none';a.querySelectorAll('input').forEach(function(x){x.required=phys&&['address','city','state','zip'].indexOf(x.name)>-1});
      document.getElementById('co').style.display=c.length?'':'none';
      lines.querySelectorAll('button').forEach(function(b){b.onclick=function(){var all=load(),i=+b.getAttribute('data-i'),d=b.getAttribute('data-d');
        var cc=all.filter(function(l){return P[l.h]&&P[l.h].variants[l.v]});
        if(d==='x')cc.splice(i,1);else{cc[i].q+=+d;if(cc[i].q<1)cc.splice(i,1)}save(cc);render()}});
      window.__summary=c.map(function(l){var v=P[l.h].variants[l.v];return l.q+' x '+P[l.h].title+(v.t==='Default Title'?'':' ['+v.t+']')+' @ '+money(v.p)}).join('\n');window.__total=sub;
    }
    render();
    document.getElementById('co').addEventListener('submit',function(e){
      e.preventDefault();var f=e.target,fd=new FormData(f);
      var body={_subject:'New HSW365 order - '+money(window.__total),_template:'table',_captcha:'false',Order:window.__summary,Total:money(window.__total)};
      fd.forEach(function(v,k){body[k]=v});
      var btn=f.querySelector('button');btn.disabled=true;btn.textContent='Sending...';
      fetch('https://formsubmit.co/ajax/hsw365media@gmail.com',{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json'},body:JSON.stringify(body)})
      .then(function(r){return r.json()}).then(function(){
        var pay=body.pay;var how=pay.indexOf('Cash')>-1?'Send '+money(window.__total)+' to Cash App <b>$hsw365</b>':'Send '+money(window.__total)+' by Zelle to <b>856-796-8081</b> or <b>elvintorressr@gmail.com</b>';
        var d=document.getElementById('done');d.hidden=false;d.innerHTML='<h3>Order received</h3><p>'+how+'. Put your name in the payment note. Your order ships or is delivered as soon as payment lands.</p>';
        f.style.display='none';save([]);lines.innerHTML='';
      }).catch(function(){btn.disabled=false;btn.textContent='Place order';alert('Could not send. Email hsw365media@gmail.com with your cart.')});
    });
  });
}
})();
