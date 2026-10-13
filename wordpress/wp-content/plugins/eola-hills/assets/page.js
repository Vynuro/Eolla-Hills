/* Progressive enhancement: native Elementor content remains visible without JS. */
(() => {
  if (document.body.classList.contains('elementor-editor-active') || new URLSearchParams(location.search).has('elementor-preview')) return;
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const animations = new Set();
  function animate(el,frames,options) {
    if(reduced.matches || !el.animate) return;
    const motion=el.animate(frames,options); animations.add(motion);
    motion.onfinish=()=>animations.delete(motion);
  }
  reduced.addEventListener('change',()=>{if(reduced.matches){animations.forEach(a=>a.finish());animations.clear();}});
  const header=document.querySelector('.eola-header');
  const photo=document.querySelector('.eola-hero-media');
  const progress=document.createElement('div'); progress.className='eola-read-progress'; progress.setAttribute('aria-hidden','true'); document.body.append(progress);
  let scheduled=false;
  const update=()=>{
    scheduled=false;
    const range=document.documentElement.scrollHeight-innerHeight;
    progress.style.setProperty('--eola-progress',range>0?Math.min(1,scrollY/range):0);
    header?.classList.toggle('is-scrolled',scrollY>30);
    if(photo) photo.style.setProperty('--eola-photo-shift',reduced.matches?'0px':`${Math.min(14,scrollY*.035)}px`);
  };
  addEventListener('scroll',()=>{if(!scheduled){scheduled=true;requestAnimationFrame(update)}},{passive:true});
  addEventListener('resize',update); update();
  const reveal=new IntersectionObserver(entries=>entries.forEach(entry=>{
    if(!entry.isIntersecting) return;
    reveal.unobserve(entry.target);
    animate(entry.target,[{opacity:.15,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:700,easing:'cubic-bezier(.2,.7,.2,1)'});
  }),{threshold:.08});
  document.querySelectorAll('.eola-reveal-section .elementor-widget-heading, .eola-reveal-section .elementor-widget-image').forEach(el=>reveal.observe(el));
  document.querySelectorAll('.eola-hero-copy > .elementor-element').forEach((el,i)=>animate(el,[{opacity:0,transform:'translateY(20px)'},{opacity:1,transform:'translateY(0)'}],{duration:850,delay:i*80,easing:'cubic-bezier(.2,.7,.2,1)',fill:'backwards'}));
  const counters=new IntersectionObserver(entries=>entries.forEach(entry=>{
    if(!entry.isIntersecting) return;
    counters.unobserve(entry.target);
    const text=entry.target, original=text.textContent, target=Number(original.replace(/,/g,''));
    if(reduced.matches||!Number.isFinite(target)) return;
    const decimals=original.includes('.')?original.split('.')[1].length:0, start=performance.now();
    const tick=now=>{
      if(reduced.matches){text.textContent=original;return;}
      const fraction=Math.min(1,(now-start)/1000), eased=1-Math.pow(1-fraction,3);
      text.textContent=(target*eased).toLocaleString('en-US',{minimumFractionDigits:decimals,maximumFractionDigits:decimals});
      if(fraction<1) requestAnimationFrame(tick); else text.textContent=original;
    };requestAnimationFrame(tick);
  }),{threshold:.6});
  document.querySelectorAll('.eola-stat-count .elementor-heading-title').forEach(el=>counters.observe(el));
  document.querySelectorAll('a[href^="#"]').forEach(link=>link.addEventListener('click',event=>{
    if(link.closest('.eola-filter'))return;
    const target=document.getElementById(link.hash.slice(1)); if(!target)return;
    event.preventDefault(); target.scrollIntoView({behavior:reduced.matches?'instant':'smooth',block:'start'});
    history.replaceState(null,'',link.hash);
    target.setAttribute('tabindex','-1'); target.focus({preventScroll:true});
  }));
  const nav=[...document.querySelectorAll('.eola-nav-link a')];
  const active=new IntersectionObserver(entries=>entries.forEach(entry=>{
    if(!entry.isIntersecting)return;
    nav.forEach(a=>a.hash===`#${entry.target.id}`?a.setAttribute('aria-current','location'):a.removeAttribute('aria-current'));
  }),{rootMargin:'-20% 0px -55% 0px'});
  nav.forEach(a=>{const el=document.getElementById(a.hash.slice(1));if(el)active.observe(el)});
  const filters=[...document.querySelectorAll('.eola-filter')], tiles=[...document.querySelectorAll('.eola-gallery-tile')];
  filters.forEach((filter,index)=>{
    const link=filter.querySelector('a');if(!link)return;
    link.setAttribute('role','button');link.setAttribute('aria-pressed',index===0?'true':'false');
    const category=[...filter.classList].find(c=>c.startsWith('eola-filter-'))?.replace('eola-filter-','');
    const apply=event=>{
      event.preventDefault();
      filters.forEach(f=>f.querySelector('a')?.setAttribute('aria-pressed',f===filter?'true':'false'));
      tiles.forEach(tile=>{tile.hidden=category!=='all'&&!tile.classList.contains(`eola-category-${category}`);if(!tile.hidden)animate(tile,[{opacity:.2,transform:'translateY(12px)'},{opacity:1,transform:'translateY(0)'}],{duration:350})});update();
    };
    link.addEventListener('click',apply);
    link.addEventListener('keydown',event=>{if(event.code==='Space')apply(event)});
  });
})();
