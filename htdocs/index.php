<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$loggedIn  = (bool) current_user();
$adminErr  = isset($_GET['admin_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#e9e1c8">
<meta name="color-scheme" content="light dark">
<title>The Stacks — Apps, Folders &amp; GitHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,600;9..144,700&family=Space+Mono:wght@400;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root{
    --paper: #e9e1c8;
    --paper-card: #f8f3e4;
    --ink: #2b2a24;
    --ink-soft: #5c584a;
    --forest: #3f5c4e;
    --rust: #a1492a;
    --mustard: #cf9a2e;
    --line: #cabf9c;
    --line-strong: #a8987a;
    --tape: #d9c9a3;
    --surface: #fffdf6;
    --radius-card: 3px;
  }
  html[data-theme="dark"]{
    --paper: #1b1a16;
    --paper-card: #242219;
    --ink: #ece4d0;
    --ink-soft: #b3aa90;
    --forest: #6ba187;
    --rust: #e08a5f;
    --mustard: #e6b955;
    --line: #47422f;
    --line-strong: #5c5640;
    --tape: #3c3826;
    --surface: #2c2919;
  }
  *{ box-sizing:border-box; }
  html,body{ height:100%; }
  body{
    margin:0;
    background: var(--paper);
    color: var(--ink);
    font-family:'Inter', sans-serif;
    transition: background .15s ease, color .15s ease;
  }
  h1,h2,h3,.brand-name,.hero-title{ font-family:'Fraunces', serif; }
  .mono, .tab, .badge, .eyebrow, .btn{ font-family:'Space Mono', monospace; }

  .grain{
    position:fixed; inset:0; pointer-events:none; z-index:0;
    opacity:0.55;
    background-image:url("assets/grain.png");
    background-size:96px 96px;
  }

  a{ color:inherit; }

  #login-screen{
    position:relative; z-index:1; min-height:100vh;
    display:flex; align-items:center; justify-content:center; padding:24px;
  }
  .login-card{
    width:100%; max-width:420px;
    background: var(--paper-card);
    border:1.5px solid var(--ink);
    border-radius: var(--radius-card);
    padding:38px 34px 30px;
    position:relative;
    box-shadow: 6px 6px 0 rgba(43,42,36,0.12);
  }
  .tape{
    position:absolute; top:-14px; left:50%; transform:translateX(-50%) rotate(-2deg);
    width:88px; height:26px;
    background: var(--tape);
    opacity:0.85;
    box-shadow: 0 1px 2px rgba(0,0,0,0.15);
  }
  .stamp{
    width:52px; height:52px;
    border:2px solid var(--rust);
    border-radius:50%;
    display:flex; align-items:center; justify-content:center;
    margin-bottom:18px;
    transform: rotate(-6deg);
    color: var(--rust);
  }
  .stamp svg{ width:26px; height:26px; }
  .hero-title{
    font-size:30px; font-weight:600; margin:0 0 6px; line-height:1.1;
  }
  .login-card p.sub{ color: var(--ink-soft); font-size:14px; margin:0 0 24px; line-height:1.55; }
  .eyebrow{
    font-size:10.5px; letter-spacing:.08em; text-transform:uppercase;
    color: var(--forest); margin-bottom:6px; display:block;
  }
  .field{ margin-bottom:16px; }
  .field label{ display:block; font-size:12px; color: var(--ink-soft); margin-bottom:6px; }
  input[type="text"], input[type="url"], input[type="password"]{
    width:100%;
    background: var(--surface);
    border:1.5px solid var(--line);
    color: var(--ink);
    padding:11px 13px;
    border-radius:2px;
    font-size:14px;
    font-family:'Inter', sans-serif;
    outline:none;
    transition: border-color .15s ease;
  }
  input:focus{ border-color: var(--forest); }
  .btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    border:1.5px solid var(--ink); cursor:pointer;
    font-size:12.5px; letter-spacing:.03em; text-transform:uppercase;
    border-radius:2px; padding:12px 20px;
    background: transparent; color: var(--ink);
    transition: transform .08s ease, background .15s ease, color .15s ease;
  }
  .btn:active{ transform: translate(2px,2px); }
  .btn-primary{ background: var(--ink); color: var(--paper-card); width:100%; border-color: var(--ink); }
  .btn-primary:hover{ background: var(--forest); border-color: var(--forest); }
  .btn-ghost{ background:transparent; color: var(--ink); }
  .btn-ghost:hover{ background: rgba(43,42,36,0.06); }
  .btn-rust{ border-color: var(--rust); color: var(--rust); }
  .btn-rust:hover{ background: var(--rust); color:#fff8ee; }
  .btn-small{ padding:7px 12px; font-size:11px; }
  .btn[disabled]{ opacity:0.55; cursor:not-allowed; }
  .login-note{ margin-top:20px; font-size:11.5px; color: var(--ink-soft); line-height:1.6; }
  .admin-toggle-row{ text-align:center; margin-top:18px; }
  .admin-toggle-row button{
    background:none; border:none; color: var(--ink-soft); font-family:'Space Mono',monospace;
    font-size:11.5px; letter-spacing:.03em; text-decoration:underline; text-underline-offset:3px; cursor:pointer;
  }
  .admin-toggle-row button:hover{ color: var(--rust); }
  .hidden{ display:none !important; }

  #app{ display:none; position:relative; z-index:1; min-height:100vh; }
  header.top{
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 28px; border-bottom:2px solid var(--ink);
    background: var(--paper);
    position:sticky; top:0; z-index:20;
    flex-wrap:wrap; gap:14px;
  }
  .brand{ display:flex; align-items:center; gap:12px; }
  .brand-mark{ width:34px; height:34px; border:2px solid var(--ink); border-radius:50%; display:flex; align-items:center; justify-content:center; transform:rotate(-4deg); }
  .brand-mark svg{ width:18px; height:18px; }
  .brand-name{ font-size:20px; font-weight:600; }
  .user-chip{ display:flex; align-items:center; gap:10px; font-size:12.5px; color: var(--ink-soft); font-family:'Space Mono',monospace; }
  .user-dot{
    width:26px; height:26px; border-radius:50%; border:1.5px solid var(--ink);
    color: var(--ink); font-weight:700; font-size:11px; font-family:'Space Mono',monospace;
    display:flex; align-items:center; justify-content:center; background: var(--mustard);
  }
  .admin-pill{
    font-size:10px; letter-spacing:.05em; text-transform:uppercase;
    border:1px solid var(--rust); color: var(--rust);
    padding:2px 8px; border-radius:20px;
  }
  .link-btn{
    background:none; border:none; color: var(--ink-soft); font-size:12.5px; cursor:pointer;
    text-decoration:underline; text-underline-offset:3px; font-family:'Space Mono',monospace;
  }
  .link-btn:hover{ color: var(--rust); }

  main{ max-width:1180px; margin:0 auto; padding:34px 28px 90px; }

  .tabs{ display:flex; gap:0; border-bottom:2px solid var(--ink); margin-bottom:28px; flex-wrap:wrap; }
  .tab{
    border:none; background:transparent; color: var(--ink-soft);
    font-size:12.5px; letter-spacing:.04em; text-transform:uppercase;
    padding:12px 22px; cursor:pointer; border-bottom:3px solid transparent; margin-bottom:-2px;
  }
  .tab.active{ color: var(--ink); border-bottom-color: var(--rust); }

  .section-head{ display:flex; align-items:baseline; justify-content:space-between; margin-bottom:20px; gap:12px; flex-wrap:wrap; }
  .section-head h2{ font-size:21px; margin:0; font-weight:600; }
  .section-head .count{ color: var(--ink-soft); font-family:'Space Mono',monospace; font-size:13px; margin-left:8px; }
  .section-desc{ color: var(--ink-soft); font-size:12.5px; margin:-14px 0 20px; }

  .grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(196px, 1fr)); gap:22px; }
  .card{
    position:relative;
    background: var(--paper-card);
    border:1.5px solid var(--ink);
    border-radius: var(--radius-card);
    padding:16px 14px 14px;
    display:flex; flex-direction:column; gap:10px;
    box-shadow: 4px 4px 0 rgba(43,42,36,0.10);
    transition: transform .12s ease, box-shadow .12s ease;
  }
  .card::after{
    content:"";
    position:absolute;
    inset:5px;
    border:1px dashed var(--line-strong);
    border-radius:2px;
    pointer-events:none;
  }
  .card::before{
    content:"";
    position:absolute;
    top:-1.5px; left:-1.5px; right:-1.5px; bottom:-1.5px;
    border-radius: var(--radius-card);
    background:
      linear-gradient(var(--paper-card), var(--paper-card)) padding-box,
      repeating-linear-gradient(-45deg, var(--rust) 0 3px, var(--mustard) 3px 6px) border-box;
    -webkit-mask:
      linear-gradient(#000 0 0) padding-box,
      linear-gradient(#000 0 0);
    -webkit-mask-composite: xor;
    mask-composite: exclude;
    border: 2px solid transparent;
    opacity:0;
    transition: opacity .15s ease;
    pointer-events:none;
    z-index:-1;
  }
  .grid > .card:nth-child(3n+1){ transform: rotate(-0.6deg); }
  .grid > .card:nth-child(3n+2){ transform: rotate(0.4deg); }
  .grid > .card:nth-child(3n){ transform: rotate(-0.3deg); }
  @media (hover:hover){
    .card:hover{ transform: rotate(0deg) translateY(-3px); box-shadow: 6px 8px 0 rgba(43,42,36,0.16); }
    .card:hover::before{ opacity:1; }
  }

  .card-header{
    display:flex; align-items:center; gap:8px;
    margin:-16px -14px 4px;
    padding:8px 12px;
    border-bottom:1.5px dashed var(--line-strong);
    background: rgba(207,154,46,0.10);
  }
  .card-header.builtin-header{ background: rgba(63,92,78,0.08); }
  .card-header-avatar{
    width:22px; height:22px; border-radius:50%; flex-shrink:0;
    border:1.5px solid var(--ink);
    display:flex; align-items:center; justify-content:center;
    font-family:'Space Mono',monospace; font-size:9.5px; font-weight:700;
    background: var(--mustard); color: var(--ink);
  }
  .card-header.builtin-header .card-header-avatar{ background: var(--forest); color:#fff8ee; }
  .card-header-text{ line-height:1.2; overflow:hidden; }
  .card-header-name{ font-size:11px; font-weight:700; color: var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .card-header-date{ font-size:9.5px; color: var(--ink-soft); font-family:'Space Mono',monospace; }

  .polaroid{
    background: var(--surface); border:1px solid var(--line); padding:6px 6px 0;
  }
  .card-logo-wrap{ width:100%; aspect-ratio:1/1; overflow:hidden; background:#efe8d3; display:flex; align-items:center; justify-content:center; }
  .card-logo-wrap img{ width:100%; height:100%; object-fit:cover; display:block; }
  .card-logo-fallback{
    width:100%; height:100%; display:flex; align-items:center; justify-content:center;
    font-family:'Fraunces',serif; font-size:26px; font-weight:600; color: var(--paper-card);
  }
  .polaroid-cap{ text-align:center; padding:6px 2px 8px; font-family:'Space Mono',monospace; font-size:9.5px; color: var(--ink-soft); letter-spacing:.03em; }

  .card-name{ font-size:15px; font-weight:600; line-height:1.25; word-break:break-word; }
  .card-desc{ font-size:12px; color: var(--ink-soft); line-height:1.4; }
  .card-meta{ font-size:10.5px; color: var(--ink-soft); font-family:'Space Mono',monospace; }

  .badge{
    align-self:flex-start;
    font-size:9px; letter-spacing:.05em; text-transform:uppercase;
    border:1px solid var(--line-strong); color: var(--ink-soft);
    padding:2px 8px; border-radius:20px;
  }
  .badge.builtin{ border-color: var(--forest); color: var(--forest); }

  .card-actions-row{ display:flex; gap:8px; margin-top:2px; flex-wrap:wrap; align-items:center; }
  .icon-btn{
    width:28px; height:28px; border-radius:2px;
    border:1.5px solid var(--line-strong);
    background: var(--surface); color: var(--ink-soft); cursor:pointer;
    display:flex; align-items:center; justify-content:center; font-size:13px;
  }
  .icon-btn:hover{ border-color: var(--ink); color: var(--ink); }
  .icon-btn.del{ border-color: var(--rust); color: var(--rust); }
  .icon-btn.del:hover{ background: var(--rust); color:#fff8ee; }

  .add-tile{
    border:2px dashed var(--line-strong);
    background: transparent;
    color: var(--ink-soft);
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
    min-height:220px; cursor:pointer;
    font-family:'Space Mono',monospace; font-size:11.5px; letter-spacing:.04em; text-transform:uppercase;
    box-shadow:none;
  }
  .add-tile:hover{ color: var(--forest); border-color: var(--forest); transform:none; }
  .add-tile .plus{ font-size:26px; font-family:'Fraunces',serif; font-weight:400; }

  .no-file-note{ font-size:10.5px; color: var(--ink-soft); font-style:italic; }
  .file-size-note{ font-size:10px; color: var(--ink-soft); font-family:'Space Mono',monospace; }

  .overlay{
    display:none; position:fixed; inset:0;
    background: rgba(43,42,36,0.55);
    align-items:center; justify-content:center; padding:20px; z-index:50;
  }
  .overlay.open{ display:flex; }
  .modal{
    width:100%; max-width:440px;
    background: var(--paper-card);
    border:1.5px solid var(--ink);
    border-radius: var(--radius-card);
    padding:28px 26px 22px;
    box-shadow: 8px 8px 0 rgba(43,42,36,0.2);
    max-height: 90vh; overflow-y:auto;
  }
  .modal h3{ margin:0 0 4px; font-size:19px; font-weight:600; }
  .modal .sub{ color: var(--ink-soft); font-size:12.5px; margin:0 0 20px; font-family:'Inter',sans-serif; }
  .logo-preview-row{ display:flex; align-items:center; gap:14px; margin-bottom:16px; }
  .logo-preview-box{ width:56px; height:56px; border:1.5px solid var(--ink); background:var(--surface); flex-shrink:0; }
  .logo-preview-box img{ width:100%; height:100%; object-fit:cover; display:none; }
  .logo-preview-box .fb{ width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-family:'Fraunces',serif; color: var(--ink-soft); }
  .file-btn{
    font-size:11.5px; color: var(--ink-soft); font-family:'Space Mono',monospace;
    border:1.5px solid var(--line-strong); background: var(--surface);
    padding:9px 12px; border-radius:2px; cursor:pointer; text-transform:uppercase; letter-spacing:.03em;
  }
  .file-btn:hover{ border-color: var(--ink); color: var(--ink); }
  .modal-actions{ display:flex; gap:10px; margin-top:20px; }
  .modal-actions .btn{ flex:1; }
  .hint{ font-size:11px; color: var(--ink-soft); margin-top:5px; }
  .file-chosen{ font-size:11.5px; color: var(--forest); margin-top:6px; font-family:'Space Mono',monospace; word-break:break-all; }
  .file-error{ font-size:11.5px; color: var(--rust); margin-top:6px; font-family:'Space Mono',monospace; }

  .theme-toggle{
    position:fixed; top:18px; right:18px; z-index:60;
    width:38px; height:38px; border-radius:50%;
    border:1.5px solid var(--ink); background: var(--paper-card); color: var(--ink);
    display:flex; align-items:center; justify-content:center; cursor:pointer;
    box-shadow: 3px 3px 0 rgba(43,42,36,0.12);
  }
  .theme-toggle:hover{ border-color: var(--forest); color: var(--forest); }
  .theme-toggle svg{ width:18px; height:18px; }
  .theme-toggle .icon-moon{ display:none; }
  html[data-theme="dark"] .theme-toggle .icon-sun{ display:none; }
  html[data-theme="dark"] .theme-toggle .icon-moon{ display:flex; }
  header.top .theme-toggle{ position:static; box-shadow:none; }

  @media (max-width:560px){
    header.top{ padding:16px; }
    main{ padding:24px 16px 60px; }
    .grid{ grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); }
  }

  /* =====================================================================
     APP POLISH — smooth scrolling, native feel, mobile layout
     ===================================================================== */
  html{ -webkit-text-size-adjust:100%; }
  html,body{ overscroll-behavior-y:contain; }
  *{ -webkit-tap-highlight-color:transparent; }
  a,button,.tab,.card,.icon-btn,.file-btn,label,select{ touch-action:manipulation; }
  body{
    -webkit-font-smoothing:antialiased;
    padding-left:env(safe-area-inset-left); padding-right:env(safe-area-inset-right);
    padding-bottom:env(safe-area-inset-bottom);
  }
  body.modal-open{ overflow:hidden; }
  #login-screen, #admin-login-screen, #app{ min-height:100vh !important; min-height:100dvh !important; }
  .theme-toggle{ top:calc(18px + env(safe-area-inset-top)); }
  header.top .theme-toggle{ top:auto; }

  /* smooth light/dark switch */
  html.theme-anim, html.theme-anim *{
    transition: background-color .28s ease, color .28s ease, border-color .28s ease, box-shadow .28s ease !important;
  }

  /* cards: springy press + soft entrance */
  .card{ transition: transform .2s cubic-bezier(.2,.8,.2,1), box-shadow .2s ease; will-change:auto; }
  .card:active{ transform: scale(.985) !important; box-shadow: 2px 2px 0 rgba(43,42,36,0.10); }
  .add-tile:active{ transform: scale(.97) !important; }
  @media (hover:none){ .card::before{ display:none; } }
  @keyframes stk-rise{ from{ opacity:0; transform: translateY(14px) scale(.98); } }
  .card.rise{ animation: stk-rise .42s cubic-bezier(.2,.8,.2,1) backwards; animation-delay: calc(var(--i,0) * 40ms); }
  .card.leaving{ opacity:0; transform: scale(.92) !important; transition: opacity .2s ease, transform .2s ease; }
  @keyframes stk-panel{ from{ opacity:0; transform: translateY(8px); } }
  .panel-enter{ animation: stk-panel .28s cubic-bezier(.2,.8,.2,1); }

  /* loading skeletons (instead of a blank screen while the board loads) */
  .card.skeleton{ pointer-events:none; min-height:230px; gap:12px; }
  .card.skeleton::after, .card.skeleton::before{ display:none; }
  .sk-line, .sk-block{
    border-radius:3px;
    background: linear-gradient(100deg, var(--line) 30%, var(--paper-card) 50%, var(--line) 70%);
    background-size: 220% 100%;
    animation: stk-shimmer 1.2s linear infinite;
    opacity:.7;
  }
  .sk-line{ height:11px; } .sk-line.short{ width:55%; }
  .sk-block{ aspect-ratio:1/1; width:100%; }
  @keyframes stk-shimmer{ from{ background-position: 120% 0; } to{ background-position: -120% 0; } }

  .empty-state{
    grid-column:1 / -1; text-align:center; padding:34px 16px;
    border:2px dashed var(--line-strong); color: var(--ink-soft); font-size:13px;
  }
  .empty-state p{ margin:0 0 14px; }

  /* toasts (replace the ugly browser alert() boxes) */
  .toast-host{
    position:fixed; left:0; right:0; z-index:200; pointer-events:none;
    bottom:calc(18px + env(safe-area-inset-bottom)); display:flex; flex-direction:column; align-items:center; gap:8px; padding:0 16px;
  }
  .toast{
    max-width:420px; width:100%;
    background: var(--ink); color: var(--paper-card);
    font:600 12.5px/1.4 'Inter',sans-serif; padding:12px 16px; border-radius:3px;
    box-shadow:4px 4px 0 rgba(43,42,36,.2);
    opacity:0; transform: translateY(14px); transition: opacity .22s ease, transform .22s cubic-bezier(.2,.8,.2,1);
  }
  .toast.show{ opacity:1; transform:none; }
  .toast.error{ background: var(--rust); color:#fff8ee; }

  /* dialogs: fade + slide, bottom sheet on phones */
  .overlay{
    display:flex; visibility:hidden; opacity:0;
    transition: opacity .22s ease, visibility 0s linear .22s;
    -webkit-overflow-scrolling:touch;
  }
  .overlay.open{ visibility:visible; opacity:1; transition: opacity .22s ease; }
  .modal{ transform: translateY(18px) scale(.98); transition: transform .3s cubic-bezier(.2,.8,.2,1); max-height:90vh; max-height:90dvh; }
  .overlay.open .modal{ transform:none; }
  .modal.small{ max-width:360px; }
  .modal.small .sub{ margin-bottom:6px; line-height:1.5; }

  /* upload progress */
  .progress{ margin-top:16px; }
  .progress .track{ height:10px; border:1.5px solid var(--ink); background: var(--surface); border-radius:2px; overflow:hidden; }
  .progress .bar{ height:100%; width:0; background: repeating-linear-gradient(-45deg, var(--rust) 0 6px, var(--mustard) 6px 12px); transition: width .2s linear; }
  .progress .label{ margin-top:6px; font:11px 'Space Mono',monospace; color: var(--ink-soft); }

  /* tabs scroll sideways on narrow phones instead of wrapping */
  .tabs{ scrollbar-width:none; }
  .tabs::-webkit-scrollbar{ display:none; }

  @media (max-width:560px){
    header.top{ padding:calc(12px + env(safe-area-inset-top)) 16px 12px; flex-wrap:wrap; gap:10px; }
    header.top .brand{ flex:1; min-width:0; }
    .user-chip{ order:3; width:100%; padding-top:10px; border-top:1px dashed var(--line-strong); min-width:0; }
    #user-name-label{ max-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .user-chip .link-btn{ margin-left:auto; }
    .tabs{ flex-wrap:nowrap; overflow-x:auto; -webkit-overflow-scrolling:touch; margin-left:-16px; margin-right:-16px; padding:0 8px; }
    .tab{ flex:1 0 auto; padding:13px 16px; }
    .grid{ gap:16px; }
    input[type="text"], input[type="url"], input[type="password"]{ font-size:16px; }   /* no zoom-on-focus */
    .overlay{ align-items:flex-end; padding:0; }
    .modal{
      max-width:none; border-radius:16px 16px 0 0; border-bottom:0; box-shadow:0 -8px 30px rgba(0,0,0,.25);
      transform: translateY(100%); max-height:92vh; max-height:92dvh;
      padding-bottom: calc(22px + env(safe-area-inset-bottom));
    }
    .modal.small{ border-radius:16px 16px 0 0; }
    .overlay.open .modal{ transform:none; }
  }
  @media (pointer:coarse){
    .btn{ min-height:44px; }
    .btn-small{ min-height:36px; }
    .icon-btn{ width:38px; height:38px; font-size:16px; }
    .file-btn{ min-height:40px; }
  }
  @media (max-height:480px) and (orientation:landscape){
    header.top{ padding-top:8px; padding-bottom:8px; position:static; }
    main{ padding-top:16px; }
    .login-card{ padding:22px 24px 18px; }
  }
  @media (prefers-reduced-motion:reduce){
    *, *::before, *::after{ animation-duration:.01ms !important; animation-iteration-count:1 !important; transition-duration:.01ms !important; }
  }
</style>
</head>
<body>
<div class="grain"></div>
<div class="toast-host" id="toast-host" aria-live="polite"></div>

<button type="button" class="theme-toggle" id="theme-toggle" title="Toggle dark / light mode" aria-label="Toggle dark / light mode">
  <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.4M12 19.1v2.4M4.4 4.4l1.7 1.7M17.9 17.9l1.7 1.7M2.5 12h2.4M19.1 12h2.4M4.4 19.6l1.7-1.7M17.9 6.1l1.7-1.7"/></svg>
  <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 14.5A8.5 8.5 0 1 1 9.5 4a6.8 6.8 0 0 0 10.5 10.5Z"/></svg>
</button>

<script>
  (function(){
    var stored = null;
    try { stored = localStorage.getItem("stacks_theme"); } catch(e){}
    var theme = stored === "dark" || stored === "light"
      ? stored
      : (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light");
    document.documentElement.setAttribute("data-theme", theme);

    // keeps the browser/Android status bar the same colour as the page
    function sync(t){
      var m = document.querySelector('meta[name="theme-color"]');
      if(m) m.setAttribute("content", t === "dark" ? "#1b1a16" : "#e9e1c8");
      try { if(window.AndroidOffline && window.AndroidOffline.theme) window.AndroidOffline.theme(t); } catch(e){}
    }

    document.addEventListener("DOMContentLoaded", function(){
      sync(theme);
      var btn = document.getElementById("theme-toggle");
      if(!btn) return;
      // when signed in, the toggle lives in the header (not floating over the user chip)
      var brand = document.querySelector("header.top .brand");
      if(brand && brand.parentNode) brand.parentNode.insertBefore(btn, brand.nextSibling);
      btn.addEventListener("click", function(){
        var root = document.documentElement;
        var next = root.getAttribute("data-theme") === "dark" ? "light" : "dark";
        root.classList.add("theme-anim");
        root.setAttribute("data-theme", next);
        try { localStorage.setItem("stacks_theme", next); } catch(e){}
        sync(next);
        setTimeout(function(){ root.classList.remove("theme-anim"); }, 360);
      });
    });
  })();
</script>

<?php if (!$loggedIn): ?>
<!-- ================= USER LOGIN ================= -->
<div id="login-screen">
  <div class="login-card">
    <div class="tape"></div>
    <div class="stamp">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <path d="M4 17V7a2 2 0 0 1 2-2h5l2 2h5a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/>
      </svg>
    </div>
    <span class="eyebrow">the stacks</span>
    <h1 class="hero-title">Sign in to<br>the board</h1>
    <p class="sub">Type your name so the board knows who added each app, folder, GitHub repo, or website link.</p>
    <form id="login-form" method="post" action="login.php">
      <div class="field">
        <label for="username">Your name</label>
        <input type="text" id="username" name="username" placeholder="Enter your Name" autocomplete="off" required>
      </div>
      <button type="submit" class="btn btn-primary">Enter the board</button>
    </form>
    <p class="login-note">Built-in apps, folders and repos can't be removed by regular members — everything else you add is yours to edit.</p>
    <div class="admin-toggle-row">
      <button type="button" id="show-admin-login">ADMIN ONLY →</button>
    </div>
  </div>
</div>

<!-- ================= ADMIN LOGIN ================= -->
<div id="admin-login-screen" class="hidden" style="position:relative; z-index:1; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px;">
  <div class="login-card">
    <div class="tape" style="background: var(--rust); opacity:0.55;"></div>
    <div class="stamp" style="border-color: var(--rust); color: var(--rust);">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6">
        <path d="M12 2 20 6v6c0 5-3.4 8.4-8 10-4.6-1.6-8-5-8-10V6l8-4Z"/>
      </svg>
    </div>
    <span class="eyebrow" style="color:var(--rust);">restricted</span>
    <h1 class="hero-title">Admin<br>sign in</h1>
    <p class="sub">Admin access for board moderation.</p>
    <form id="admin-login-form" method="post" action="admin_login.php">
      <div class="field">
        <label for="admin-name">Admin username</label>
        <input type="text" id="admin-name" name="admin_name" placeholder="Enter Admin User" autocomplete="off" required>
      </div>
      <div class="field">
        <label for="admin-password">Admin password</label>
        <input type="password" id="admin-password" name="admin_password" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-rust" style="width:100%;">Enter as admin</button>
    </form>
    <p class="login-note" id="admin-error" style="color:var(--rust); <?= $adminErr ? '' : 'display:none;' ?>">Wrong username or password. Try again.</p>
    <div class="admin-toggle-row">
      <button type="button" id="show-user-login">← Back to regular sign in</button>
    </div>
  </div>
</div>

<script>
  document.getElementById("show-admin-login").addEventListener("click", function(){
    document.getElementById("login-screen").style.display = "none";
    document.getElementById("admin-login-screen").classList.remove("hidden");
  });
  document.getElementById("show-user-login").addEventListener("click", function(){
    document.getElementById("admin-login-screen").classList.add("hidden");
    document.getElementById("login-screen").style.display = "flex";
  });
  <?php if ($adminErr): ?>
  document.getElementById("login-screen").style.display = "none";
  document.getElementById("admin-login-screen").classList.remove("hidden");
  <?php endif; ?>
</script>

<?php else: ?>
<!-- ================= APP ================= -->
<div id="app" style="display:block;">
  <header class="top">
    <div class="brand">
      <div class="brand-mark">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 17V7a2 2 0 0 1 2-2h5l2 2h5a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2Z"/></svg>
      </div>
      <div class="brand-name">The Stacks</div>
    </div>
    <div class="user-chip">
      <div class="user-dot" id="user-initial" style="background:<?= is_admin() ? '#f0d9c4' : '#cf9a2e' ?>"><?= h(initials(current_user())) ?></div>
      <span id="user-name-label"><?= h(current_user()) ?></span>
      <span class="admin-pill <?= is_admin() ? '' : 'hidden' ?>" id="admin-pill">admin</span>
      <a class="link-btn" href="logout.php" id="switch-user">switch user</a>
    </div>
  </header>

  <main>
    <div class="tabs">
      <button class="tab active" data-tab="apps">Apps</button>
      <button class="tab" data-tab="folders">Folders</button>
      <button class="tab" data-tab="github">GitHub</button>
      <button class="tab" data-tab="websites">Websites</button>
    </div>

    <section id="panel-apps">
      <div class="section-head"><h2>APPS<span class="count" id="apps-count"></span></h2></div>
      <p class="section-desc">Android applications — upload the actual APK (up to 10GB) so members can download and install it.</p>
      <div class="grid" id="apps-grid"></div>
    </section>

    <section id="panel-folders" class="hidden">
      <div class="section-head"><h2>FOLDERS<span class="count" id="folders-count"></span></h2></div>
      <p class="section-desc">ADD YOUR FOLDER HERE</p>
      <div class="grid" id="folders-grid"></div>
    </section>

    <section id="panel-github" class="hidden">
      <div class="section-head"><h2>GITHUB<span class="count" id="github-count"></span></h2></div>
      <p class="section-desc">ADD YOUR GITHUB HERE</p>
      <div class="grid" id="github-grid"></div>
    </section>

    <section id="panel-websites" class="hidden">
      <div class="section-head"><h2>WEBSITES<span class="count" id="websites-count"></span></h2></div>
      <p class="section-desc">ADD YOUR WEBSITE HERE</p>
      <div class="grid" id="websites-grid"></div>
    </section>
  </main>
</div>

<!-- ================= ADD/EDIT MODAL ================= -->
<div class="overlay" id="overlay">
  <div class="modal">
    <h3 id="modal-title">Add app</h3>
    <p class="sub" id="modal-sub">Give it a name and an icon.</p>
    <form id="item-form">
      <input type="hidden" id="item-id" value="">
      <input type="hidden" id="item-type" value="apps">
      <div class="logo-preview-row">
        <div class="logo-preview-box">
          <img id="logo-preview">
          <div id="logo-preview-fallback" class="fb">?</div>
        </div>
        <div>
          <button type="button" class="file-btn" id="pick-logo">Upload icon</button>
          <input type="file" id="logo-file" accept="image/*" style="display:none;">
        </div>
      </div>
      <div class="field">
        <label for="item-name">Name</label>
        <input type="text" id="item-name" placeholder="Enter your name " required>
      </div>
      <div class="field">
        <label for="item-desc">Short description (optional)</label>
        <input type="text" id="item-desc" placeholder="Short description (optional)">
      </div>
      <div class="field" id="link-field">
        <label for="item-link" id="link-label">Link</label>
        <input type="url" id="item-link" placeholder="https://example.com">
      </div>
      <div class="field" id="package-file-field">
        <label id="package-file-label" for="item-package-file">Upload APK file</label>
        <button type="button" class="file-btn" id="pick-package">Choose file</button>
        <input type="file" id="item-package-file" style="display:none;">
        <div class="file-chosen" id="package-file-name"></div>
        <div class="file-error hidden" id="package-file-error"></div>
        <div class="hint" id="package-hint">Files are uploaded to the server (MySQL stores the details, the file itself is saved on disk). Up to 10GB is allowed, but actual space depends on the server's free disk space and PHP's upload limits.</div>
      </div>
      <div class="field" id="logo-url-field">
        <label for="item-logo-url">Or paste an icon image URL</label>
        <input type="url" id="item-logo-url" placeholder="https://example.com/icon.png">
      </div>
      <div class="progress hidden" id="upload-progress">
        <div class="track"><div class="bar" id="upload-bar"></div></div>
        <div class="label" id="upload-label"></div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" id="cancel-modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="save-item">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- ================= CONFIRM DIALOG ================= -->
<div class="overlay" id="confirm-overlay">
  <div class="modal small" role="alertdialog" aria-labelledby="confirm-title">
    <h3 id="confirm-title">Delete?</h3>
    <p class="sub" id="confirm-text"></p>
    <div class="modal-actions">
      <button type="button" class="btn btn-ghost" id="confirm-cancel">Cancel</button>
      <button type="button" class="btn btn-rust" id="confirm-ok">Delete</button>
    </div>
  </div>
</div>

<script>
(function(){
  "use strict";

  var CURRENT_USER = <?= json_encode(current_user()) ?>;
  var IS_ADMIN = <?= json_encode(is_admin()) ?>;
  var OFFLINE_COPY = !!window.__stacksOffline;     // true when the Android app shows a saved copy with no internet
  var TYPES = ["apps","folders","github","websites"];

  var TYPE_CONFIG = {
    apps:     { hasFile: true,  fileLabel: "Upload APK file",  linkLabel: "Play Store / website link (optional)", linkRequired: false, isLink: false },
    folders:  { hasFile: true,  fileLabel: "Upload file to share", linkLabel: "Reference link (optional)", linkRequired: false, isLink: false },
    github:   { hasFile: false, fileLabel: "",                   linkLabel: "GitHub repository URL", linkRequired: true, isLink: true },
    websites: { hasFile: false, fileLabel: "",                   linkLabel: "Website URL", linkRequired: true, isLink: true }
  };

  var state = { apps: [], folders: [], github: [], websites: [], activeTab: "apps", animate: true };

  var el = {
    tabs: document.querySelectorAll(".tab"),
    panels: { apps: document.getElementById("panel-apps"), folders: document.getElementById("panel-folders"), github: document.getElementById("panel-github"), websites: document.getElementById("panel-websites") },
    grids: { apps: document.getElementById("apps-grid"), folders: document.getElementById("folders-grid"), github: document.getElementById("github-grid"), websites: document.getElementById("websites-grid") },
    counts: { apps: document.getElementById("apps-count"), folders: document.getElementById("folders-count"), github: document.getElementById("github-count"), websites: document.getElementById("websites-count") },
    overlay: document.getElementById("overlay"),
    modalTitle: document.getElementById("modal-title"),
    modalSub: document.getElementById("modal-sub"),
    itemForm: document.getElementById("item-form"),
    itemId: document.getElementById("item-id"),
    itemType: document.getElementById("item-type"),
    itemName: document.getElementById("item-name"),
    itemDesc: document.getElementById("item-desc"),
    itemLink: document.getElementById("item-link"),
    linkLabel: document.getElementById("link-label"),
    itemLogoUrl: document.getElementById("item-logo-url"),
    logoPreview: document.getElementById("logo-preview"),
    logoPreviewFallback: document.getElementById("logo-preview-fallback"),
    pickLogo: document.getElementById("pick-logo"),
    logoFile: document.getElementById("logo-file"),
    packageFileField: document.getElementById("package-file-field"),
    packageFileLabel: document.getElementById("package-file-label"),
    pickPackage: document.getElementById("pick-package"),
    itemPackageFile: document.getElementById("item-package-file"),
    packageFileName: document.getElementById("package-file-name"),
    packageFileError: document.getElementById("package-file-error"),
    cancelModal: document.getElementById("cancel-modal"),
    saveItem: document.getElementById("save-item"),
    progress: document.getElementById("upload-progress"),
    progressBar: document.getElementById("upload-bar"),
    progressLabel: document.getElementById("upload-label")
  };
  var cf = {
    overlay: document.getElementById("confirm-overlay"),
    title: document.getElementById("confirm-title"),
    text: document.getElementById("confirm-text"),
    ok: document.getElementById("confirm-ok"),
    cancel: document.getElementById("confirm-cancel")
  };

  // ---------- helpers ----------
  function escapeHtml(str){
    return String(str||"").replace(/[&<>"']/g, function(c){ return { "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[c]; });
  }
  function initials(name){ return (name||"?").trim().slice(0,1).toUpperCase(); }
  function formatDate(ts){
    if(!ts) return "";
    try{ return new Date(ts.replace(" ","T")).toLocaleDateString(undefined, { month:"short", day:"numeric", year:"numeric" }); }
    catch(e){ return ""; }
  }
  function formatBytes(bytes){
    if(bytes === null || bytes === undefined) return "";
    bytes = Number(bytes);
    if(bytes === 0) return "0 B";
    var units = ["B","KB","MB","GB","TB"];
    var i = Math.floor(Math.log(bytes) / Math.log(1024));
    i = Math.min(i, units.length - 1);
    return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 2) + " " + units[i];
  }
  var PALETTE = ["#a1492a","#3f5c4e","#cf9a2e","#5c584a","#7a5230"];
  function colorFor(name){
    var sum=0; for(var i=0;i<name.length;i++){ sum+=name.charCodeAt(i); }
    return PALETTE[sum % PALETTE.length];
  }
  function fallbackLogoHtml(name){
    return '<div class="card-logo-fallback" style="background:' + colorFor(name) + '">' + initials(name) + '</div>';
  }
  function tick(){ try{ if(window.AndroidOffline && window.AndroidOffline.haptic) window.AndroidOffline.haptic(); }catch(e){} }
  function typeWord(type){ return type === "apps" ? "app" : type === "folders" ? "folder" : type === "github" ? "repo" : "website"; }

  function toast(msg, kind){
    var host = document.getElementById("toast-host");
    if(!host){ return; }
    var t = document.createElement("div");
    t.className = "toast" + (kind === "error" ? " error" : "");
    t.textContent = msg;
    host.appendChild(t);
    requestAnimationFrame(function(){ requestAnimationFrame(function(){ t.classList.add("show"); }); });
    setTimeout(function(){
      t.classList.remove("show");
      setTimeout(function(){ if(t.parentNode) t.parentNode.removeChild(t); }, 260);
    }, 3200);
  }
  function offlineNotice(){ toast("You're offline — connect to the internet to do that.", "error"); }

  // ---------- back button / swipe-back closes dialogs first ----------
  var layers = [], suppressPop = 0;
  function openLayer(closeNow){
    layers.push(closeNow);
    try{ history.pushState({ stk: layers.length }, ""); }catch(e){}
  }
  function closeLayer(closeNow){
    var i = layers.lastIndexOf(closeNow);
    if(i === -1){ closeNow(); return; }
    layers.splice(i, 1);
    closeNow();
    suppressPop++;
    try{ history.back(); }catch(e){ suppressPop--; }
  }
  window.addEventListener("popstate", function(){
    if(suppressPop > 0){ suppressPop--; return; }
    var fn = layers.pop();
    if(fn) fn();
  });
  function syncBodyLock(){
    document.body.classList.toggle("modal-open", el.overlay.classList.contains("open") || cf.overlay.classList.contains("open"));
  }

  // ---------- in-page confirm (replaces the browser's own confirm() box) ----------
  function askConfirm(title, text, okLabel){
    return new Promise(function(resolve){
      var result = false, settled = false;
      cf.title.textContent = title;
      cf.text.textContent = text;
      cf.ok.textContent = okLabel || "Delete";
      function closeNow(){
        if(settled) return;
        settled = true;
        cf.overlay.classList.remove("open");
        syncBodyLock();
        cf.ok.onclick = cf.cancel.onclick = cf.overlay.onclick = null;
        resolve(result);
      }
      cf.overlay.classList.add("open");
      syncBodyLock();
      openLayer(closeNow);
      cf.ok.onclick = function(){ result = true; closeLayer(closeNow); };
      cf.cancel.onclick = function(){ result = false; closeLayer(closeNow); };
      cf.overlay.onclick = function(ev){ if(ev.target === cf.overlay){ result = false; closeLayer(closeNow); } };
    });
  }

  // ---------- Load from the PHP/MySQL API ----------
  function hasCards(){ return !!document.querySelector(".card[data-id]"); }

  function showSkeletons(){
    TYPES.forEach(function(type){
      var h = "";
      for(var i=0;i<4;i++){
        h += '<div class="card skeleton"><div class="sk-line short"></div><div class="sk-block"></div><div class="sk-line"></div><div class="sk-line short"></div></div>';
      }
      el.grids[type].innerHTML = h;
    });
  }

  function showLoadError(){
    if(hasCards()){ toast("Couldn't refresh — showing what's already loaded.", "error"); return; }
    TYPES.forEach(function(type){
      el.grids[type].innerHTML = '<div class="empty-state"><p>Can\u2019t reach the server right now.</p>' +
        '<button type="button" class="btn btn-small" data-retry="1">Try again</button></div>';
    });
  }

  function loadAll(){
    if(OFFLINE_COPY) return;
    fetch("api/list.php", { credentials: "same-origin", cache: "no-store" })
      .then(function(r){
        if(r.status === 401){ location.reload(); throw new Error("auth"); }   // session ended -> sign-in screen
        if(!r.ok) throw new Error("http " + r.status);
        return r.json();
      })
      .then(function(data){
        state.apps = data.apps || []; state.folders = data.folders || [];
        state.github = data.github || []; state.websites = data.websites || [];
        window.__stacksReady = true;
        render();
      })
      .catch(function(err){
        if(err && err.message === "auth") return;
        showLoadError();
      });
  }

  function cardHtml(item, type, idx){
    var cfg = TYPE_CONFIG[type];
    var canEdit = IS_ADMIN || (item.added_by && item.added_by === CURRENT_USER);
    var canDelete = IS_ADMIN;
    var logo = item.logo
      ? '<div class="card-logo-wrap"><img src="' + escapeHtml(item.logo) + '" alt="" loading="lazy" decoding="async"></div>'
      : '<div class="card-logo-wrap">' + fallbackLogoHtml(item.name) + '</div>';

    var headerName = item.built_in == 1 ? "Built-in" : escapeHtml(item.added_by || "Unknown");
    var headerAvatarText = item.built_in == 1 ? "★" : initials(item.added_by || "?");
    var headerDate = formatDate(item.created_at);
    var badge =
      '<div class="card-header' + (item.built_in == 1 ? " builtin-header" : "") + '">' +
        '<div class="card-header-avatar">' + headerAvatarText + '</div>' +
        '<div class="card-header-text">' +
          '<div class="card-header-name">' + (item.built_in == 1 ? "Built-in" : "Sent by " + headerName) + '</div>' +
          (headerDate ? '<div class="card-header-date">' + headerDate + '</div>' : '') +
        '</div>' +
      '</div>';

    var primaryAction = "";
    if(cfg.isLink){
      // whole card behaves like a link
    } else if(cfg.hasFile){
      if(item.file_name){
        primaryAction = '<a class="btn btn-small btn-ghost" href="api/download.php?id='+item.id+'">Download</a>' +
          (item.file_size ? ' <span class="file-size-note">' + formatBytes(item.file_size) + '</span>' : '');
      } else {
        primaryAction = '<span class="no-file-note">No file uploaded yet</span>';
      }
      if(item.link){
        primaryAction += ' <a class="btn btn-small btn-ghost" href="'+escapeHtml(item.link)+'" target="_blank" rel="noopener noreferrer">Visit</a>';
      }
    }

    var manageBtns = "";
    if(canEdit || canDelete){
      manageBtns = '<div class="card-actions-row" style="margin-top:auto;">';
      if(canEdit){ manageBtns += '<button type="button" class="icon-btn edit" title="Edit" aria-label="Edit" data-type="'+type+'" data-id="'+item.id+'">&#9998;</button>'; }
      if(canDelete){ manageBtns += '<button type="button" class="icon-btn del" title="Delete (admin)" aria-label="Delete" data-type="'+type+'" data-id="'+item.id+'">&times;</button>'; }
      manageBtns += '</div>';
    }

    var body =
      badge +
      '<div class="polaroid">' + logo + '<div class="polaroid-cap">' + escapeHtml(item.name.toUpperCase()) + '</div></div>' +
      '<div class="card-name">' + escapeHtml(item.name) + '</div>' +
      (item.description ? '<div class="card-desc">' + escapeHtml(item.description) + '</div>' : '') +
      (primaryAction ? '<div class="card-actions-row">' + primaryAction + '</div>' : '') +
      manageBtns;

    var cls = "card" + (state.animate ? " rise" : "");
    var delay = "--i:" + Math.min(idx, 12) + ";";
    if(cfg.isLink){
      return '<a class="'+cls+'" href="'+escapeHtml(item.link || "#")+'" target="_blank" rel="noopener noreferrer" data-id="'+item.id+'" style="'+delay+'text-decoration:none;">' + body + '</a>';
    }
    return '<div class="'+cls+'" data-id="'+item.id+'" style="'+delay+'">' + body + '</div>';
  }

  function render(){
    TYPES.forEach(function(type){
      var list = state[type];
      var addLabel = "Add " + typeWord(type);
      el.grids[type].innerHTML = list.map(function(it, i){ return cardHtml(it, type, i); }).join("") +
        '<button type="button" class="card add-tile' + (state.animate ? " rise" : "") + '" style="--i:' + Math.min(list.length, 12) + ';" data-add="'+type+'"><span class="plus">+</span>'+addLabel+'</button>';
      el.counts[type].textContent = "(" + list.length + ")";
    });
    state.animate = false;      // entrance animation only on the first draw, not after every save
  }

  // ---------- one delegated click handler (works for freshly drawn cards, no re-binding) ----------
  document.addEventListener("click", function(ev){
    var t = ev.target;
    if(!t || !t.closest) return;

    var retry = t.closest("[data-retry]");
    if(retry){ state.animate = true; showSkeletons(); loadAll(); return; }

    var add = t.closest("[data-add]");
    if(add){ if(OFFLINE_COPY){ offlineNotice(); return; } tick(); openModal(add.getAttribute("data-add")); return; }

    var ed = t.closest(".icon-btn.edit");
    if(ed){ ev.preventDefault(); ev.stopPropagation(); if(OFFLINE_COPY){ offlineNotice(); return; } tick(); openModal(ed.getAttribute("data-type"), ed.getAttribute("data-id")); return; }

    var del = t.closest(".icon-btn.del");
    if(del){ ev.preventDefault(); ev.stopPropagation(); if(OFFLINE_COPY){ offlineNotice(); return; } deleteItem(del.getAttribute("data-type"), del.getAttribute("data-id")); return; }
  });

  function deleteItem(type, id){
    if(!IS_ADMIN) return;
    var item = state[type].find(function(i){ return String(i.id) === String(id); });
    if(!item) return;
    askConfirm('Delete "' + item.name + '"?', "This can't be undone.", "Delete").then(function(yes){
      if(!yes) return;
      var fd = new FormData();
      fd.append("id", id);
      fetch("api/delete.php", { method: "POST", body: fd, credentials: "same-origin" })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if(res.error){ toast(res.error, "error"); return; }
          var card = el.grids[type].querySelector('.card[data-id="' + id + '"]');
          if(card) card.classList.add("leaving");
          toast("Deleted.");
          setTimeout(loadAll, 220);
        })
        .catch(function(){ toast("Couldn't delete this item — check your connection.", "error"); });
    });
  }

  // ---------- tabs ----------
  function switchTab(tab, animate){
    state.activeTab = tab;
    el.tabs.forEach(function(t){
      var on = t.getAttribute("data-tab") === tab;
      t.classList.toggle("active", on);
      if(on && animate && t.scrollIntoView){ try{ t.scrollIntoView({ inline: "center", block: "nearest", behavior: "smooth" }); }catch(e){} }
    });
    Object.keys(el.panels).forEach(function(k){ el.panels[k].classList.toggle("hidden", k !== tab); });
    if(animate){
      var p = el.panels[tab];
      p.classList.remove("panel-enter"); void p.offsetWidth; p.classList.add("panel-enter");
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
    try{ localStorage.setItem("stacks_tab", tab); }catch(e){}
  }
  el.tabs.forEach(function(t){ t.addEventListener("click", function(){ tick(); switchTab(t.getAttribute("data-tab"), true); }); });

  // ---------- Modal ----------
  var pendingLogoFile = null;
  var currentXhr = null;

  function openModal(type, id){
    pendingLogoFile = null;
    el.itemForm.reset();
    el.itemType.value = type;
    el.itemId.value = "";
    el.logoPreview.style.display = "none";
    el.logoPreviewFallback.style.display = "flex";
    el.logoPreviewFallback.textContent = "?";
    el.packageFileName.textContent = "";
    el.packageFileError.classList.add("hidden");
    el.packageFileError.textContent = "";
    el.progress.classList.add("hidden");
    el.progressBar.style.width = "0";

    var cfg = TYPE_CONFIG[type];
    el.linkLabel.textContent = cfg.linkLabel;
    el.itemLink.required = cfg.linkRequired;
    el.packageFileField.classList.toggle("hidden", !cfg.hasFile);
    el.packageFileLabel.textContent = cfg.fileLabel;

    if(id){
      var item = state[type].find(function(i){ return String(i.id) === String(id); });
      if(!item) return;
      el.itemId.value = item.id;
      el.modalTitle.textContent = "Edit " + typeWord(type);
      el.modalSub.textContent = "Update the details below.";
      el.saveItem.textContent = "Save changes";
      el.itemName.value = item.name;
      el.itemDesc.value = item.description || "";
      el.itemLink.value = item.link || "";
      if(item.file_name){
        el.packageFileName.textContent = "Current file: " + item.file_name + (item.file_size ? " (" + formatBytes(item.file_size) + ")" : "") + " — choose a new file to replace it.";
      }
      if(item.logo){
        el.logoPreview.src = item.logo; el.logoPreview.style.display = "block"; el.logoPreviewFallback.style.display = "none";
      } else {
        el.logoPreviewFallback.textContent = initials(item.name);
        el.logoPreviewFallback.style.background = colorFor(item.name);
      }
    } else {
      el.modalTitle.textContent = "Add " + typeWord(type);
      el.modalSub.textContent = (type === "github" || type === "websites") ? "Paste the link." : "Give it a name, an icon, and the file to share.";
      el.saveItem.textContent = "Add";
    }
    el.overlay.classList.add("open");
    syncBodyLock();
    openLayer(closeModalNow);
    setTimeout(function(){ try{ el.itemName.focus({ preventScroll: true }); }catch(e){ el.itemName.focus(); } }, 280);
  }

  function closeModalNow(){
    if(currentXhr){ try{ currentXhr.abort(); }catch(e){} currentXhr = null; }
    el.overlay.classList.remove("open");
    syncBodyLock();
    pendingLogoFile = null;
  }
  function closeModal(){ closeLayer(closeModalNow); }

  el.cancelModal.addEventListener("click", function(){
    if(currentXhr){ try{ currentXhr.abort(); }catch(e){} return; }     // cancel a running upload first
    closeModal();
  });
  el.overlay.addEventListener("click", function(ev){ if(ev.target === el.overlay && !currentXhr) closeModal(); });
  document.addEventListener("keydown", function(ev){
    if(ev.key !== "Escape") return;
    if(cf.overlay.classList.contains("open")){ cf.cancel.click(); }
    else if(el.overlay.classList.contains("open") && !currentXhr){ closeModal(); }
  });
  // keep the field you are typing in visible above the on-screen keyboard
  el.overlay.addEventListener("focusin", function(ev){
    var t = ev.target;
    if(t && /^(INPUT|TEXTAREA)$/.test(t.tagName)){
      setTimeout(function(){ try{ t.scrollIntoView({ block: "center", behavior: "smooth" }); }catch(e){} }, 320);
    }
  });

  el.pickLogo.addEventListener("click", function(){ el.logoFile.click(); });
  el.logoFile.addEventListener("change", function(){
    var file = el.logoFile.files[0]; if(!file) return;
    pendingLogoFile = file;
    var reader = new FileReader();
    reader.onload = function(e){
      el.logoPreview.src = e.target.result; el.logoPreview.style.display = "block"; el.logoPreviewFallback.style.display = "none";
    };
    reader.readAsDataURL(file);
  });
  el.itemLogoUrl.addEventListener("input", function(){
    var v = el.itemLogoUrl.value.trim();
    if(v){ el.logoPreview.src = v; el.logoPreview.style.display = "block"; el.logoPreviewFallback.style.display = "none"; }
  });

  var MAX_FILE_SIZE = <?= (int) MAX_FILE_SIZE ?>;
  el.pickPackage.addEventListener("click", function(){ el.itemPackageFile.click(); });
  el.itemPackageFile.addEventListener("change", function(){
    var file = el.itemPackageFile.files[0]; if(!file) return;
    el.packageFileError.classList.add("hidden");
    el.packageFileError.textContent = "";
    if(file.size > MAX_FILE_SIZE){
      el.packageFileError.textContent = "That file is " + formatBytes(file.size) + " — the limit is 10GB.";
      el.packageFileError.classList.remove("hidden");
      el.itemPackageFile.value = "";
      return;
    }
    el.packageFileName.textContent = "Selected: " + file.name + " (" + formatBytes(file.size) + ")";
  });

  // upload with a real progress bar (a plain fetch() gives no feedback on big APKs)
  function sendForm(fd, showProgress){
    return new Promise(function(resolve, reject){
      var xhr = new XMLHttpRequest();
      currentXhr = xhr;
      xhr.open("POST", "api/save.php");
      xhr.withCredentials = true;
      if(showProgress){
        el.progress.classList.remove("hidden");
        el.progressBar.style.width = "0";
        el.progressLabel.textContent = "Starting upload…";
        xhr.upload.onprogress = function(e){
          if(!e.lengthComputable) return;
          var pct = Math.min(100, Math.round(e.loaded / e.total * 100));
          el.progressBar.style.width = pct + "%";
          el.progressLabel.textContent = pct >= 100 ? "Finishing up…" : pct + "%  ·  " + formatBytes(e.loaded) + " of " + formatBytes(e.total);
        };
      }
      xhr.onload = function(){
        currentXhr = null;
        var res = null;
        try{ res = JSON.parse(xhr.responseText); }catch(e){}
        if(!res){ reject(new Error(xhr.status === 413 ? "That file is bigger than the server allows." : "The server sent back something unexpected. If you uploaded a big file, check PHP's upload limits.")); return; }
        if(res.error === "Invalid type" && showProgress){ res.error = "The server dropped the upload — the file is probably larger than PHP's post_max_size / upload_max_filesize."; }
        resolve(res);
      };
      xhr.onerror = function(){ currentXhr = null; reject(new Error("Couldn't reach the server — check your connection and try again.")); };
      xhr.onabort = function(){ currentXhr = null; reject(new Error("aborted")); };
      xhr.send(fd);
    });
  }
  function setSaving(on, label){
    el.saveItem.disabled = on;
    el.saveItem.textContent = on ? "Saving…" : label;
    if(!on){ el.progress.classList.add("hidden"); }
  }

  el.itemForm.addEventListener("submit", function(ev){
    ev.preventDefault();
    if(OFFLINE_COPY){ offlineNotice(); return; }
    var type = el.itemType.value;
    var cfg = TYPE_CONFIG[type];
    var name = el.itemName.value.trim();
    var link = el.itemLink.value.trim();
    if(!name) return;
    if(cfg.linkRequired && !link) return;

    var editing = !!el.itemId.value;
    var originalLabel = el.saveItem.textContent;
    setSaving(true, originalLabel);

    var fd = new FormData();
    fd.append("type", type);
    if(el.itemId.value) fd.append("id", el.itemId.value);
    fd.append("name", name);
    fd.append("desc", el.itemDesc.value.trim());
    fd.append("link", link);
    fd.append("logo_url", el.itemLogoUrl.value.trim());
    if(pendingLogoFile) fd.append("logo_file", pendingLogoFile);
    var pkg = el.itemPackageFile.files[0];
    if(pkg) fd.append("package_file", pkg);

    sendForm(fd, !!pkg).then(function(res){
      setSaving(false, originalLabel);
      if(res.error){ toast(res.error, "error"); return; }
      closeModal();
      toast(editing ? "Changes saved." : "Added to the board.");
      tick();
      loadAll();
    }).catch(function(err){
      setSaving(false, originalLabel);
      if(err && err.message === "aborted"){ toast("Upload cancelled."); return; }
      toast((err && err.message) || "Couldn't save this item — try again.", "error");
    });
  });

  // ---------- start ----------
  var savedTab = null;
  try{ savedTab = localStorage.getItem("stacks_tab"); }catch(e){}
  switchTab(TYPE_CONFIG[savedTab] ? savedTab : "apps", false);
  if(!OFFLINE_COPY){
    if(!hasCards()) showSkeletons();
    loadAll();
  }
})();
</script>
<?php endif; ?>
</body>
</html>
