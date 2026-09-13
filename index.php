<?php
// Fastsmshub Landing Page
// Single-file landing page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Fastsmshub — Secure Online SMS Verification Numbers</title>

    <meta name="description" content="Fastsmshub provides reliable virtual phone numbers for receiving SMS verification codes across multiple countries. Fast, secure and easy to use.">
    <meta name="keywords" content="SMS verification, virtual numbers, online SMS, receive SMS online, phone verification, temporary numbers, international numbers">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#6D28D9">

    <link rel="canonical" href="https://fastsmshub.com/">

    <!-- Flaticon -->
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-brands/css/uicons-brands.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-solid-rounded/css/uicons-solid-rounded.css">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        html{
            scroll-behavior:smooth;
        }

        body{
            font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
            background:#fff;
            color:#17131f;
            overflow-x:hidden;
            line-height:1.6;
        }

        a{
            text-decoration:none;
            color:inherit;
        }

        img{
            max-width:100%;
            display:block;
        }

        :root{
            --purple:#6D28D9;
            --purple-dark:#4C1D95;
            --purple-light:#8B5CF6;
            --purple-soft:#F3E8FF;
            --purple-pale:#FAF7FF;
            --text:#17131f;
            --muted:#6B6575;
            --border:#EDE7F5;
            --white:#ffffff;
        }

        /* =========================
           NAVBAR
        ========================= */

        .navbar{
            position:fixed;
            top:0;
            left:0;
            width:100%;
            z-index:999;
            background:rgba(255,255,255,.88);
            backdrop-filter:blur(18px);
            -webkit-backdrop-filter:blur(18px);
            border-bottom:1px solid rgba(109,40,217,.08);
        }

        .nav-container{
            width:min(1180px,92%);
            margin:auto;
            height:78px;
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .logo{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:22px;
            font-weight:800;
            letter-spacing:-.7px;
        }

        .logo-icon{
            width:40px;
            height:40px;
            border-radius:12px;
            background:linear-gradient(135deg,#7C3AED,#5B21B6);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            box-shadow:0 8px 25px rgba(109,40,217,.25);
        }

        .logo span{
            color:var(--purple);
        }

        .nav-links{
            display:flex;
            align-items:center;
            gap:32px;
            font-size:14px;
            font-weight:600;
            color:#5D5668;
        }

        .nav-links a{
            transition:.25s ease;
        }

        .nav-links a:hover{
            color:var(--purple);
        }

        .nav-actions{
            display:flex;
            align-items:center;
            gap:10px;
        }

        .login-btn{
            padding:11px 17px;
            border-radius:10px;
            color:#51495e;
            font-size:14px;
            font-weight:700;
        }

        .register-btn{
            padding:12px 19px;
            border-radius:11px;
            background:var(--purple);
            color:white;
            font-size:14px;
            font-weight:700;
            box-shadow:0 8px 20px rgba(109,40,217,.22);
            transition:.25s ease;
        }

        .register-btn:hover{
            background:var(--purple-dark);
            transform:translateY(-2px);
        }

        .menu-btn{
            display:none;
            border:0;
            background:var(--purple-soft);
            color:var(--purple);
            width:42px;
            height:42px;
            border-radius:10px;
            cursor:pointer;
        }

        /* =========================
           HERO
        ========================= */

        .hero{
            position:relative;
            min-height:760px;
            padding:155px 0 90px;
            background:
                radial-gradient(circle at 12% 15%,rgba(139,92,246,.14),transparent 28%),
                radial-gradient(circle at 90% 30%,rgba(109,40,217,.10),transparent 30%),
                #fff;
            overflow:hidden;
        }

        .hero-container{
            width:min(1180px,92%);
            margin:auto;
            display:grid;
            grid-template-columns:1.02fr .98fr;
            align-items:center;
            gap:70px;
        }

        .hero-badge{
            display:inline-flex;
            align-items:center;
            gap:8px;
            padding:8px 13px;
            background:var(--purple-soft);
            color:var(--purple-dark);
            border:1px solid #E9D5FF;
            border-radius:100px;
            font-size:12px;
            font-weight:800;
            margin-bottom:22px;
        }

        .badge-dot{
            width:7px;
            height:7px;
            border-radius:50%;
            background:#22C55E;
            box-shadow:0 0 0 4px rgba(34,197,94,.12);
        }

        .hero h1{
            font-size:clamp(44px,5.4vw,73px);
            line-height:1.02;
            letter-spacing:-3.5px;
            max-width:700px;
            margin-bottom:24px;
            font-weight:850;
        }

        .hero h1 span{
            color:var(--purple);
            position:relative;
        }

        .hero h1 span:after{
            content:"";
            position:absolute;
            left:2px;
            right:2px;
            bottom:-4px;
            height:5px;
            border-radius:20px;
            background:#DDD6FE;
            transform:rotate(-1deg);
            z-index:-1;
        }

        .hero-description{
            max-width:610px;
            color:var(--muted);
            font-size:17px;
            line-height:1.8;
            margin-bottom:31px;
        }

        .hero-buttons{
            display:flex;
            align-items:center;
            gap:12px;
            flex-wrap:wrap;
        }

        .primary-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:9px;
            padding:15px 22px;
            border-radius:12px;
            background:var(--purple);
            color:#fff;
            font-weight:750;
            font-size:14px;
            box-shadow:0 12px 30px rgba(109,40,217,.24);
            transition:.25s ease;
        }

        .primary-btn:hover{
            transform:translateY(-3px);
            background:var(--purple-dark);
            box-shadow:0 17px 35px rgba(109,40,217,.30);
        }

        .secondary-btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            gap:9px;
            padding:15px 22px;
            border-radius:12px;
            border:1px solid #E6E0EC;
            background:#fff;
            color:#403849;
            font-weight:750;
            font-size:14px;
            transition:.25s ease;
        }

        .secondary-btn:hover{
            border-color:#C4B5FD;
            color:var(--purple);
            background:#FAF8FF;
        }

        .hero-trust{
            display:flex;
            align-items:center;
            gap:13px;
            margin-top:32px;
        }

        .avatars{
            display:flex;
        }

        .avatar{
            width:31px;
            height:31px;
            border-radius:50%;
            border:2px solid white;
            margin-left:-7px;
            background:linear-gradient(135deg,#C4B5FD,#6D28D9);
            display:flex;
            align-items:center;
            justify-content:center;
            color:#fff;
            font-size:10px;
            font-weight:800;
        }

        .avatar:first-child{
            margin-left:0;
        }

        .trust-text{
            font-size:12px;
            color:#746D7C;
        }

        .trust-text strong{
            color:#29232F;
        }

        /* =========================
           HERO PHONE MOCKUP
        ========================= */

        .hero-visual{
            position:relative;
            display:flex;
            justify-content:center;
            min-height:530px;
        }

        .glow{
            position:absolute;
            width:420px;
            height:420px;
            border-radius:50%;
            background:#EDE9FE;
            filter:blur(10px);
            top:50px;
            right:10px;
        }

        .phone{
            position:relative;
            width:310px;
            height:600px;
            border-radius:42px;
            background:#15121A;
            padding:9px;
            box-shadow:
                0 35px 70px rgba(52,24,82,.25),
                0 10px 25px rgba(0,0,0,.12);
            transform:rotate(3deg);
            z-index:3;
        }

        .phone-screen{
            width:100%;
            height:100%;
            border-radius:34px;
            background:#F8F6FB;
            overflow:hidden;
        }

        .phone-top{
            height:62px;
            background:#fff;
            display:flex;
            align-items:center;
            justify-content:space-between;
            padding:0 20px;
            border-bottom:1px solid #EEEAF2;
        }

        .phone-brand{
            font-size:15px;
            font-weight:800;
        }

        .phone-brand span{
            color:var(--purple);
        }

        .phone-content{
            padding:20px 16px;
        }

        .phone-welcome{
            background:linear-gradient(135deg,#6D28D9,#8B5CF6);
            color:#fff;
            border-radius:19px;
            padding:18px;
            margin-bottom:16px;
            box-shadow:0 10px 22px rgba(109,40,217,.18);
        }

        .phone-welcome small{
            opacity:.8;
            font-size:10px;
        }

        .phone-welcome h3{
            margin-top:3px;
            font-size:21px;
        }

        .phone-balance{
            margin-top:13px;
            background:rgba(255,255,255,.14);
            padding:8px 10px;
            border-radius:9px;
            font-size:11px;
        }

        .number-card{
            background:#fff;
            border:1px solid #ECE8F1;
            padding:13px;
            border-radius:15px;
            margin-bottom:10px;
        }

        .number-top{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:8px;
        }

        .country{
            font-size:11px;
            font-weight:750;
        }

        .active-dot{
            width:7px;
            height:7px;
            background:#22C55E;
            border-radius:50%;
            display:inline-block;
            margin-right:4px;
        }

        .number{
            font-size:15px;
            font-weight:800;
            letter-spacing:.3px;
        }

        .message{
            font-size:10px;
            color:#77717F;
            margin-top:4px;
        }

        .phone-nav{
            position:absolute;
            bottom:0;
            left:0;
            width:100%;
            background:#fff;
            border-top:1px solid #EDE9F1;
            height:64px;
            display:flex;
            justify-content:space-around;
            align-items:center;
        }

        .phone-nav-item{
            font-size:9px;
            color:#898290;
            display:flex;
            flex-direction:column;
            align-items:center;
            gap:4px;
        }

        .phone-nav-item.active{
            color:var(--purple);
            font-weight:700;
        }

        .phone-nav-item i{
            font-size:15px;
        }

        .floating-card{
            position:absolute;
            z-index:5;
            background:rgba(255,255,255,.94);
            backdrop-filter:blur(15px);
            border:1px solid rgba(255,255,255,.8);
            box-shadow:0 20px 45px rgba(40,20,60,.14);
            border-radius:16px;
            padding:13px 15px;
        }

        .floating-card.one{
            left:0;
            top:125px;
        }

        .floating-card.two{
            right:-8px;
            bottom:100px;
        }

        .float-row{
            display:flex;
            align-items:center;
            gap:10px;
        }

        .float-icon{
            width:35px;
            height:35px;
            border-radius:10px;
            background:#F3E8FF;
            color:var(--purple);
            display:flex;
            align-items:center;
            justify-content:center;
        }

        .float-title{
            font-size:10px;
            color:#837A8D;
        }

        .float-value{
            font-size:13px;
            font-weight:800;
            color:#27202E;
        }

        /* =========================
           STATS
        ========================= */

        .stats-section{
            padding:0 0 90px;
            background:#fff;
        }

        .stats-container{
            width:min(1080px,92%);
            margin:auto;
            display:grid;
            grid-template-columns:repeat(4,1fr);
            background:#fff;
            border:1px solid #EDE8F3;
            border-radius:22px;
            box-shadow:0 18px 45px rgba(49,25,75,.06);
            overflow:hidden;
        }

        .stat{
            padding:28px 20px;
            text-align:center;
            border-right:1px solid #EEEAF3;
        }

        .stat:last-child{
            border-right:0;
        }

        .stat h3{
            font-size:27px;
            letter-spacing:-1px;
            color:#231B2C;
        }

        .stat p{
            color:#81798B;
            font-size:12px;
            margin-top:3px;
        }

        /* =========================
           LOGO STRIP
        ========================= */

        .logo-strip{
            padding:20px 0 90px;
        }

        .logo-strip-title{
            text-align:center;
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:1.8px;
            color:#9A92A4;
            font-weight:700;
            margin-bottom:27px;
        }

        .logos{
            width:min(900px,92%);
            margin:auto;
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:25px;
            flex-wrap:wrap;
        }

        .fake-logo{
            color:#B5AEBB;
            font-size:16px;
            font-weight:800;
            letter-spacing:-.5px;
        }

        /* =========================
           SECTION GLOBAL
        ========================= */

        .section{
            padding:105px 0;
        }

        .section.soft{
            background:#FAF9FC;
        }

        .container{
            width:min(1180px,92%);
            margin:auto;
        }

        .section-heading{
            max-width:680px;
            margin:0 auto 60px;
            text-align:center;
        }

        .eyebrow{
            display:inline-block;
            color:var(--purple);
            font-size:11px;
            text-transform:uppercase;
            letter-spacing:1.5px;
            font-weight:850;
            margin-bottom:12px;
        }

        .section-heading h2{
            font-size:clamp(34px,4vw,50px);
            line-height:1.1;
            letter-spacing:-2px;
            margin-bottom:16px;
        }

        .section-heading p{
            color:var(--muted);
            font-size:15px;
        }

        /* =========================
           HOW IT WORKS
        ========================= */

        .steps{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:20px;
        }

        .step{
            position:relative;
            background:#fff;
            border:1px solid #ECE7F1;
            border-radius:22px;
            padding:31px;
            transition:.3s ease;
        }

        .step:hover{
            transform:translateY(-7px);
            border-color:#D8CCF0;
            box-shadow:0 20px 40px rgba(50,25,75,.07);
        }

        .step-number{
            width:44px;
            height:44px;
            border-radius:13px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:var(--purple-soft);
            color:var(--purple);
            font-weight:850;
            margin-bottom:25px;
        }

        .step-icon{
            position:absolute;
            top:29px;
            right:29px;
            color:#D9D0E5;
            font-size:26px;
        }

        .step h3{
            font-size:19px;
            margin-bottom:9px;
        }

        .step p{
            color:#797180;
            font-size:13px;
            line-height:1.75;
        }

        /* =========================
           FEATURES
        ========================= */

        .feature-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:18px;
        }

        .feature{
            padding:29px;
            border:1px solid #ECE7F1;
            border-radius:20px;
            background:#fff;
        }

        .feature-icon{
            width:47px;
            height:47px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#F3E8FF;
            color:var(--purple);
            border-radius:13px;
            font-size:20px;
            margin-bottom:19px;
        }

        .feature h3{
            font-size:17px;
            margin-bottom:7px;
        }

        .feature p{
            font-size:13px;
            color:#7C7485;
            line-height:1.7;
        }

        /* =========================
           APP SECTION
        ========================= */

        .app-section{
            background:linear-gradient(135deg,#4C1D95,#6D28D9);
            color:#fff;
            overflow:hidden;
            position:relative;
        }

        .app-section:before{
            content:"";
            position:absolute;
            width:500px;
            height:500px;
            border-radius:50%;
            border:1px solid rgba(255,255,255,.09);
            right:-150px;
            top:-200px;
        }

        .app-grid{
            display:grid;
            grid-template-columns:1fr 1fr;
            align-items:center;
            gap:80px;
        }

        .app-copy .eyebrow{
            color:#DDD6FE;
        }

        .app-copy h2{
            font-size:clamp(35px,4vw,53px);
            line-height:1.08;
            letter-spacing:-2px;
            margin-bottom:20px;
        }

        .app-copy p{
            color:#E9D5FF;
            font-size:15px;
            line-height:1.8;
            max-width:550px;
        }

        .app-list{
            margin:26px 0 30px;
            display:grid;
            gap:12px;
        }

        .app-list-item{
            display:flex;
            align-items:center;
            gap:10px;
            font-size:13px;
            color:#F5F3FF;
        }

        .check{
            width:21px;
            height:21px;
            border-radius:50%;
            background:rgba(255,255,255,.14);
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:10px;
        }

        .store-buttons{
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .store{
            display:flex;
            align-items:center;
            gap:9px;
            border:1px solid rgba(255,255,255,.2);
            background:rgba(255,255,255,.08);
            padding:10px 14px;
            border-radius:11px;
            min-width:135px;
        }

        .store i{
            font-size:22px;
        }

        .store small{
            display:block;
            font-size:8px;
            opacity:.7;
        }

        .store strong{
            font-size:12px;
        }

        .app-phone-area{
            min-height:550px;
            display:flex;
            justify-content:center;
            align-items:center;
            position:relative;
        }

        .app-phone{
            width:275px;
            height:540px;
            border:7px solid #17131F;
            background:#F8F6FB;
            border-radius:38px;
            box-shadow:0 35px 70px rgba(0,0,0,.25);
            overflow:hidden;
            transform:rotate(-5deg);
        }

        .app-screen-top{
            background:#fff;
            padding:25px 18px 15px;
        }

        .app-screen-top small{
            color:#8C8394;
        }

        .app-screen-top h3{
            color:#221B2A;
            font-size:22px;
            margin-top:4px;
        }

        .app-card{
            margin:12px;
            padding:16px;
            border-radius:17px;
            background:linear-gradient(135deg,#6D28D9,#8B5CF6);
            color:#fff;
        }

        .app-card small{
            opacity:.75;
        }

        .app-card h3{
            font-size:25px;
            margin-top:4px;
        }

        /* =========================
           TESTIMONIALS
        ========================= */

        .testimonial-grid{
            display:grid;
            grid-template-columns:repeat(3,1fr);
            gap:18px;
        }

        .testimonial{
            padding:28px;
            border:1px solid #ECE7F1;
            background:#fff;
            border-radius:20px;
        }

        .stars{
            color:#F59E0B;
            letter-spacing:2px;
            font-size:12px;
            margin-bottom:17px;
        }

        .testimonial p{
            color:#514A58;
            font-size:14px;
            line-height:1.8;
            margin-bottom:23px;
        }

        .user{
            display:flex;
            align-items:center;
            gap:10px;
        }

        .user-avatar{
            width:38px;
            height:38px;
            border-radius:50%;
            background:linear-gradient(135deg,#C4B5FD,#7C3AED);
            color:#fff;
            display:flex;
            align-items:center;
            justify-content:center;
            font-size:11px;
            font-weight:800;
        }

        .user-name{
            font-size:12px;
            font-weight:800;
        }

        .user-handle{
            font-size:10px;
            color:#918998;
        }

        /* =========================
           RATING BAR
        ========================= */

        .rating-box{
            margin-top:45px;
            padding:25px;
            border:1px solid #E9E2F1;
            background:#FAF8FD;
            border-radius:18px;
            display:flex;
            justify-content:center;
            align-items:center;
            gap:35px;
            flex-wrap:wrap;
        }

        .rating-item{
            text-align:center;
        }

        .rating-item strong{
            display:block;
            font-size:24px;
            color:#2A2232;
        }

        .rating-item span{
            color:#817888;
            font-size:11px;
        }

        /* =========================
           CTA
        ========================= */

        .cta{
            padding:110px 0;
            background:#fff;
        }

        .cta-box{
            position:relative;
            overflow:hidden;
            background:linear-gradient(135deg,#F5F0FF,#FBFAFF);
            border:1px solid #E6DDF1;
            border-radius:30px;
            padding:65px 40px;
            text-align:center;
        }

        .cta-box:before,
        .cta-box:after{
            content:"";
            position:absolute;
            border-radius:50%;
            border:1px solid #E5D8F5;
        }

        .cta-box:before{
            width:280px;
            height:280px;
            left:-140px;
            top:-150px;
        }

        .cta-box:after{
            width:220px;
            height:220px;
            right:-100px;
            bottom:-130px;
        }

        .cta-box h2{
            position:relative;
            font-size:clamp(34px,4vw,49px);
            letter-spacing:-2px;
            line-height:1.1;
            margin-bottom:15px;
        }

        .cta-box p{
            position:relative;
            max-width:580px;
            margin:0 auto 27px;
            color:#716979;
            font-size:14px;
        }

        .cta-box .primary-btn{
            position:relative;
        }

        /* =========================
           FOOTER
        ========================= */

        footer{
            background:#17121F;
            color:#fff;
            padding:70px 0 25px;
        }

        .footer-grid{
            width:min(1180px,92%);
            margin:auto;
            display:grid;
            grid-template-columns:1.5fr 1fr 1fr 1fr;
            gap:50px;
            padding-bottom:55px;
        }

        .footer-brand p{
            color:#9C94A6;
            font-size:13px;
            line-height:1.8;
            max-width:330px;
            margin:17px 0 22px;
        }

        .footer-socials{
            display:flex;
            gap:9px;
        }

        .social{
            width:37px;
            height:37px;
            border:1px solid #352D3D;
            border-radius:10px;
            display:flex;
            align-items:center;
            justify-content:center;
            color:#C4BBCD;
            transition:.2s;
        }

        .social:hover{
            color:#fff;
            background:var(--purple);
            border-color:var(--purple);
        }

        .footer-col h4{
            font-size:13px;
            margin-bottom:17px;
        }

        .footer-col a{
            display:block;
            color:#948B9F;
            font-size:12px;
            margin-bottom:11px;
            transition:.2s;
        }

        .footer-col a:hover{
            color:#fff;
        }

        .footer-bottom{
            width:min(1180px,92%);
            margin:auto;
            padding-top:22px;
            border-top:1px solid #302A37;
            display:flex;
            justify-content:space-between;
            gap:15px;
            flex-wrap:wrap;
            color:#7E7687;
            font-size:11px;
        }

        .footer-logo{
            color:#fff;
        }

        .footer-logo span{
            color:#A78BFA;
        }

        /* =========================
           MOBILE
        ========================= */

        @media(max-width:900px){

            .nav-links,
            .nav-actions{
                display:none;
            }

            .menu-btn{
                display:flex;
                align-items:center;
                justify-content:center;
            }

            .hero{
                padding-top:125px;
            }

            .hero-container{
                grid-template-columns:1fr;
                text-align:center;
                gap:45px;
            }

            .hero-description{
                margin-left:auto;
                margin-right:auto;
            }

            .hero-buttons,
            .hero-trust{
                justify-content:center;
            }

            .hero-visual{
                min-height:560px;
            }

            .steps,
            .feature-grid,
            .testimonial-grid{
                grid-template-columns:1fr;
            }

            .app-grid{
                grid-template-columns:1fr;
                gap:30px;
            }

            .app-copy{
                text-align:center;
            }

            .app-copy p{
                margin:auto;
            }

            .app-list{
                width:max-content;
                max-width:100%;
                margin-left:auto;
                margin-right:auto;
                text-align:left;
            }

            .store-buttons{
                justify-content:center;
            }

            .stats-container{
                grid-template-columns:repeat(2,1fr);
            }

            .stat:nth-child(2){
                border-right:0;
            }

            .stat:nth-child(3),
            .stat:nth-child(4){
                border-top:1px solid #EEEAF3;
            }

            .footer-grid{
                grid-template-columns:1fr 1fr;
            }
        }

        @media(max-width:600px){

            .nav-container{
                height:70px;
            }

            .logo{
                font-size:19px;
            }

            .logo-icon{
                width:36px;
                height:36px;
            }

            .hero{
                min-height:auto;
                padding-bottom:70px;
            }

            .hero h1{
                font-size:43px;
                letter-spacing:-2.5px;
            }

            .hero-description{
                font-size:14px;
            }

            .primary-btn,
            .secondary-btn{
                width:100%;
            }

            .hero-buttons{
                width:100%;
            }

            .phone{
                width:270px;
                height:525px;
            }

            .floating-card.one{
                left:-3px;
                top:105px;
            }

            .floating-card.two{
                right:-3px;
                bottom:70px;
            }

            .logos{
                justify-content:center;
            }

            .section{
                padding:75px 0;
            }

            .section-heading{
                margin-bottom:38px;
            }

            .section-heading h2{
                font-size:35px;
            }

            .stats-container{
                border-radius:17px;
            }

            .stat{
                padding:22px 12px;
            }

            .stat h3{
                font-size:22px;
            }

            .app-section{
                padding-bottom:60px;
            }

            .app-phone-area{
                min-height:480px;
            }

            .app-phone{
                width:250px;
                height:490px;
            }

            .cta{
                padding:70px 0;
            }

            .cta-box{
                padding:48px 22px;
                border-radius:23px;
            }

            .footer-grid{
                grid-template-columns:1fr;
                gap:32px;
            }

            .footer-bottom{
                flex-direction:column;
            }
        }

    </style>
</head>

<body>

<!-- =========================
     NAVBAR
========================= -->

<header class="navbar">

    <div class="nav-container">

        <a href="/" class="logo">
            <div class="logo-icon">
                <i class="fi fi-sr-mobile-button"></i>
            </div>

            Fast<span>smshub</span>
        </a>

        <nav class="nav-links">
            <a href="#home">Home</a>
            <a href="#how-it-works">How it Works</a>
            <a href="#features">Features</a>
            <a href="#testimonials">Testimonials</a>
            <a href="#contact">Contact</a>
        </nav>

        <div class="nav-actions">
            <a href="login.php" class="login-btn">Log in</a>
            <a href="register.php" class="register-btn">Register</a>
        </div>

        <button class="menu-btn" onclick="toggleMenu()">
            <i class="fi fi-rr-menu-burger"></i>
        </button>

    </div>

</header>


<!-- =========================
     HERO
========================= -->

<section class="hero" id="home">

    <div class="hero-container">

        <div class="hero-copy">

            <div class="hero-badge">
                <span class="badge-dot"></span>
                All-In-One Digital Platform

            </div>

            <h1>
                Everything Digital,
                <span>All in One Place.</span>
            </h1>

            <p class="hero-description">
Buy virtual numbers, airtime, data, social media services, Telegram premium and more — instantly and securely.

            </p>

            <div class="hero-buttons">

                <a href="register.php" class="primary-btn">
                    Get Number Now
                    <i class="fi fi-rr-arrow-up-right"></i>
                </a>

                <a href="#how-it-works" class="secondary-btn">
                    How It Works
                    <i class="fi fi-rr-play"></i>
                </a>

            </div>

            <div class="hero-trust">

                <div class="avatars">
                    <div class="avatar">JD</div>
                    <div class="avatar">MK</div>
                    <div class="avatar">AN</div>
                    <div class="avatar">LK</div>
                </div>

                <div class="trust-text">
                    <strong>5,000+</strong> customers trust Fastsmshub
                </div>

            </div>

        </div>


        <!-- PHONE -->

        <div class="hero-visual">

            <div class="glow"></div>

            <div class="floating-card one">

                <div class="float-row">

                    <div class="float-icon">
                        <i class="fi fi-sr-shield-check"></i>
                    </div>

                    <div>
                        <div class="float-title">Verification</div>
                        <div class="float-value">Secure & Active</div>
                    </div>

                </div>

            </div>


            <div class="phone">

                <div class="phone-screen">

                    <div class="phone-top">

                        <div class="phone-brand">
                            Fast<span>smshub</span>
                        </div>

                        <i class="fi fi-rr-bell"></i>

                    </div>

                    <div class="phone-content">

                        <div class="phone-welcome">

                            <small>WELCOME BACK</small>

                            <h3>Your Numbers</h3>

                            <div class="phone-balance">
                                Available Balance: $25.50
                            </div>

                        </div>


                        <div class="number-card">

                            <div class="number-top">

                                <div class="country">
                                    🇺🇸 United States
                                </div>

                                <div style="font-size:9px;color:#22C55E;">
                                    <span class="active-dot"></span>
                                    Active
                                </div>

                            </div>

                            <div class="number">
                                +1 (555) 123-4567
                            </div>

                            <div class="message">
                                Your verification code is 123456
                            </div>

                        </div>


                        <div class="number-card">

                            <div class="number-top">

                                <div class="country">
                                    🇬🇧 United Kingdom
                                </div>

                                <div style="font-size:9px;color:#22C55E;">
                                    <span class="active-dot"></span>
                                    Active
                                </div>

                            </div>

                            <div class="number">
                                +44 7911 123456
                            </div>

                            <div class="message">
                                Welcome! Your code: 789012
                            </div>

                        </div>


                        <div class="number-card">

                            <div class="number-top">

                                <div class="country">
                                    🇩🇪 Germany
                                </div>

                                <div style="font-size:9px;color:#F59E0B;">
                                    Waiting
                                </div>

                            </div>

                            <div class="number">
                                +49 151 12345678
                            </div>

                            <div class="message">
                                Waiting for SMS...
                            </div>

                        </div>

                    </div>


                    <div class="phone-nav">

                        <div class="phone-nav-item active">
                            <i class="fi fi-rr-mobile-button"></i>
                            Numbers
                        </div>

                        <div class="phone-nav-item">
                            <i class="fi fi-rr-comment-alt"></i>
                            Messages
                        </div>

                        <div class="phone-nav-item">
                            <i class="fi fi-rr-user"></i>
                            Profile
                        </div>

                    </div>

                </div>

            </div>


            <div class="floating-card two">

                <div class="float-row">

                    <div class="float-icon">
                        <i class="fi fi-sr-comment-alt"></i>
                    </div>

                    <div>
                        <div class="float-title">New SMS Received</div>
                        <div class="float-value">+1 (555) 123-4567</div>
                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     STATS
========================= -->

<section class="stats-section">

    <div class="stats-container">

        <div class="stat">
            <h3>99.9%</h3>
            <p>Platform Uptime</p>
        </div>

        <div class="stat">
            <h3>100+</h3>
            <p>Countries Available</p>
        </div>

        <div class="stat">
            <h3>5K+</h3>
            <p>Happy Customers</p>
        </div>

        <div class="stat">
            <h3>24/7</h3>
            <p>Customer Support</p>
        </div>

    </div>

</section>


<!-- =========================
     LOGO STRIP
========================= -->

<section class="logo-strip">

    <div class="logo-strip-title">
        Trusted for reliable verification
    </div>

    <div class="logos">

        <div class="fake-logo">Translator</div>
        <div class="fake-logo">Reform</div>
        <div class="fake-logo">Tuple</div>
        <div class="fake-logo">SavvyCal</div>
        <div class="fake-logo">Statamic</div>

    </div>

</section>


<!-- =========================
     HOW IT WORKS
========================= -->

<section class="section soft" id="how-it-works">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">Simple Process</span>

            <h2>How it works</h2>

            <p>
                Getting a virtual number doesn't have to be complicated.
                Start in just a few simple steps.
            </p>

        </div>


        <div class="steps">

            <div class="step">

                <div class="step-number">01</div>

                <i class="fi fi-sr-user-add step-icon"></i>

                <h3>Join Now</h3>

                <p>
                    Create your account quickly and access your dashboard.
                    Registration is simple, straightforward and hassle-free.
                </p>

            </div>


            <div class="step">

                <div class="step-number">02</div>

                <i class="fi fi-sr-wallet step-icon"></i>

                <h3>Fund Your Account</h3>

                <p>
                    Add funds securely using available payment methods.
                    Your balance becomes available for use after funding.
                </p>

            </div>


            <div class="step">

                <div class="step-number">03</div>

                <i class="fi fi-sr-mobile-button step-icon"></i>

                <h3>Choose Your Number</h3>

                <p>
                    Select a suitable virtual number from available countries
                    and services, then receive supported SMS messages.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     FEATURES
========================= -->

<section class="section" id="features">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">Why Fastsmshub</span>

            <h2>Built for speed, privacy &amp; simplicity.</h2>

            <p>
                Everything you need to manage virtual numbers and
                verification messages from one convenient platform.
            </p>

        </div>


        <div class="feature-grid">

            <div class="feature">

                <div class="feature-icon">
                    <i class="fi fi-sr-bolt"></i>
                </div>

                <h3>Swift Delivery</h3>

                <p>
                    Get access to available numbers quickly and monitor
                    incoming verification messages from your dashboard.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    <i class="fi fi-sr-shield-check"></i>
                </div>

                <h3>Privacy Focused</h3>

                <p>
                    Keep your personal phone number separate when a
                    supported service requires SMS verification.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    <i class="fi fi-sr-globe"></i>
                </div>

                <h3>Global Coverage</h3>

                <p>
                    Browse available numbers from multiple countries
                    through one easy-to-use platform.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    <i class="fi fi-sr-dashboard"></i>
                </div>

                <h3>Easy Dashboard</h3>

                <p>
                    Manage your balance, numbers and received messages
                    without complicated setup.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    <i class="fi fi-sr-lock"></i>
                </div>

                <h3>Secure Platform</h3>

                <p>
                    We focus on maintaining a dependable platform and
                    protecting account access.
                </p>

            </div>


            <div class="feature">

                <div class="feature-icon">
                    <i class="fi fi-sr-headset"></i>
                </div>

                <h3>24/7 Support</h3>

                <p>
                    Our support channels are available to help you
                    when you need assistance.
                </p>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     APP
========================= -->

<section class="section app-section">

    <div class="container">

        <div class="app-grid">

            <div class="app-copy">

                <span class="eyebrow">Mobile Experience</span>

                <h2>
                    Take Fastsmshub
                    wherever you go.
                </h2>

                <p>
                    Manage your numbers, monitor incoming SMS messages
                    and stay connected with your account from your
                    mobile device.
                </p>


                <div class="app-list">

                    <div class="app-list-item">
                        <span class="check">
                            <i class="fi fi-rr-check"></i>
                        </span>
                        Instant notifications for received SMS
                    </div>

                    <div class="app-list-item">
                        <span class="check">
                            <i class="fi fi-rr-check"></i>
                        </span>
                        Manage multiple numbers seamlessly
                    </div>

                    <div class="app-list-item">
                        <span class="check">
                            <i class="fi fi-rr-check"></i>
                        </span>
                        Convenient access to message history
                    </div>

                </div>


                <div class="store-buttons">

                    <a href="#" class="store">

                        <i class="fi fi-brands-apple"></i>

                        <div>
                            <small>Download on the</small>
                            <strong>App Store</strong>
                        </div>

                    </a>


                    <a href="#" class="store">

                        <i class="fi fi-brands-google-play"></i>

                        <div>
                            <small>GET IT ON</small>
                            <strong>Google Play</strong>
                        </div>

                    </a>

                </div>

            </div>


            <div class="app-phone-area">

                <div class="app-phone">

                    <div class="app-screen-top">

                        <small>Good morning</small>

                        <h3>Fastsmshub</h3>

                    </div>

                    <div class="app-card">

                        <small>Available Balance</small>

                        <h3>$25.50</h3>

                    </div>


                    <div class="number-card">

                        <div class="number-top">

                            <div class="country">
                                🇺🇸 United States
                            </div>

                            <span style="font-size:9px;color:#22C55E;">
                                Active
                            </span>

                        </div>

                        <div class="number">
                            +1 (555) 123-4567
                        </div>

                        <div class="message">
                            Your verification code is 123456
                        </div>

                    </div>


                    <div class="number-card">

                        <div class="number-top">

                            <div class="country">
                                🇬🇧 United Kingdom
                            </div>

                            <span style="font-size:9px;color:#22C55E;">
                                Active
                            </span>

                        </div>

                        <div class="number">
                            +44 7911 123456
                        </div>

                        <div class="message">
                            Welcome! Your code: 789012
                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</section>


<!-- =========================
     TESTIMONIALS
========================= -->

<section class="section soft" id="testimonials">

    <div class="container">

        <div class="section-heading">

            <span class="eyebrow">Testimonials</span>

            <h2>Hear from our customers</h2>

            <p>
                See why customers choose Fastsmshub for their
                virtual number and SMS verification needs.
            </p>

        </div>


        <div class="testimonial-grid">


            <div class="testimonial">

                <div class="stars">★★★★★</div>

                <p>
                    “Fastsmshub has helped me save time and manage
                    verification requirements without constantly
                    sharing my personal number.”
                </p>

                <div class="user">

                    <div class="user-avatar">LK</div>

                    <div>
                        <div class="user-name">Leonard Krasner</div>
                        <div class="user-handle">@Leonard.Krasner</div>
                    </div>

                </div>

            </div>


            <div class="testimonial">

                <div class="stars">★★★★★</div>

                <p>
                    “Getting an international number was simple.
                    The dashboard is easy to understand and the
                    whole process was seamless.”
                </p>

                <div class="user">

                    <div class="user-avatar">AS</div>

                    <div>
                        <div class="user-name">Anabelle Shein</div>
                        <div class="user-handle">@Anabelle.Shein</div>
                    </div>

                </div>

            </div>


            <div class="testimonial">

                <div class="stars">★★★★★</div>

                <p>
                    “The service is quick and convenient. I like
                    having different country options available
                    from one place.”
                </p>

                <div class="user">

                    <div class="user-avatar">ML</div>

                    <div>
                        <div class="user-name">Mike Lebron</div>
                        <div class="user-handle">@mikelebron</div>
                    </div>

                </div>

            </div>


        </div>


        <div class="rating-box">

            <div class="rating-item">
                <strong>4.9★</strong>
                <span>Customer Rating</span>
            </div>

            <div class="rating-item">
                <strong>50K+</strong>
                <span>Numbers Activated</span>
            </div>

            <div class="rating-item">
                <strong>99.9%</strong>
                <span>Platform Uptime</span>
            </div>

            <div class="rating-item">
                <strong>24/7</strong>
                <span>Support</span>
            </div>

        </div>

    </div>

</section>


<!-- =========================
     CTA
========================= -->

<section class="cta" id="contact">

    <div class="container">

        <div class="cta-box">

            <span class="eyebrow">
                Get Started Today
            </span>

            <h2>
                Your next verification<br>
                starts here.
            </h2>

            <p>
                Create your Fastsmshub account and explore available
                virtual numbers from around the world.
            </p>

            <a href="register.php" class="primary-btn">

                Create Free Account

                <i class="fi fi-rr-arrow-up-right"></i>

            </a>

        </div>

    </div>

</section>


<!-- =========================
     FOOTER
========================= -->

<footer>

    <div class="footer-grid">

        <div class="footer-brand">

            <div class="logo footer-logo">

                <div class="logo-icon">
                    <i class="fi fi-sr-mobile-button"></i>
                </div>

                Fast<span>smshub</span>

            </div>


            <p>
                Your trusted partner for convenient virtual numbers
                and reliable SMS verification services across
                supported countries.
            </p>


            <div class="footer-socials">

                <a href="#" class="social">
                    <i class="fi fi-brands-instagram"></i>
                </a>

                <a href="#" class="social">
                    <i class="fi fi-brands-telegram"></i>
                </a>

                <a href="mailto:support@fastsmshub.com" class="social">
                    <i class="fi fi-rr-envelope"></i>
                </a>

            </div>

        </div>


        <div class="footer-col">

            <h4>Platform</h4>

            <a href="#home">Home</a>
            <a href="#how-it-works">How It Works</a>
            <a href="#features">Features</a>
            <a href="#testimonials">Testimonials</a>

        </div>


        <div class="footer-col">

            <h4>Account</h4>

            <a href="register.php">Register</a>
            <a href="login.php">Log in</a>
            <a href="#">Dashboard</a>
            <a href="#">Support</a>

        </div>


        <div class="footer-col">

            <h4>Legal</h4>

            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Use</a>
            <a href="#">Disclaimer</a>
            <a href="#">Refund Policy</a>

        </div>

    </div>


    <div class="footer-bottom">

        <div>
            © <?php echo date("Y"); ?> Fastsmshub. All rights reserved. 
        </div>

        <div>
            <a href="https://wa.me/2349152620938">Developed By FemTeh NG</a>
        </div>
        <div>
            Built for a faster &amp; simpler verification experience.
        </div>

    </div>

</footer>


<!-- =========================
     MOBILE MENU
========================= -->

<div id="mobileMenu" style="
    display:none;
    position:fixed;
    z-index:998;
    top:70px;
    left:0;
    width:100%;
    background:rgba(255,255,255,.98);
    backdrop-filter:blur(20px);
    border-bottom:1px solid #EEE8F3;
    padding:20px;
    box-shadow:0 15px 35px rgba(40,20,60,.08);
">

    <a href="#home"
       onclick="closeMenu()"
       style="
       display:block;
       padding:13px 5px;
       font-size:14px;
       font-weight:700;
       color:#554D60;
    ">
        Home
    </a>

    <a href="#how-it-works"
       onclick="closeMenu()"
       style="
       display:block;
       padding:13px 5px;
       font-size:14px;
       font-weight:700;
       color:#554D60;
    ">
        How it Works
    </a>

    <a href="#features"
       onclick="closeMenu()"
       style="
       display:block;
       padding:13px 5px;
       font-size:14px;
       font-weight:700;
       color:#554D60;
    ">
        Features
    </a>

    <a href="#testimonials"
       onclick="closeMenu()"
       style="
       display:block;
       padding:13px 5px;
       font-size:14px;
       font-weight:700;
       color:#554D60;
    ">
        Testimonials
    </a>

    <a href="login.php"
       style="
       display:block;
       padding:13px 5px;
       font-size:14px;
       font-weight:700;
       color:#554D60;
    ">
        Log in
    </a>

    <a href="register.php"
       style="
       display:block;
       margin-top:8px;
       padding:13px;
       text-align:center;
       border-radius:10px;
       background:#6D28D9;
       color:white;
       font-size:14px;
       font-weight:800;
    ">
        Register
    </a>

</div>


<script>

    function toggleMenu(){

        const menu = document.getElementById("mobileMenu");

        if(menu.style.display === "block"){
            menu.style.display = "none";
        }else{
            menu.style.display = "block";
        }

    }

    function closeMenu(){

        document.getElementById("mobileMenu").style.display = "none";

    }

    /* Close mobile menu when clicking outside */

    document.addEventListener("click", function(event){

        const menu = document.getElementById("mobileMenu");
        const button = document.querySelector(".menu-btn");

        if(
            menu.style.display === "block" &&
            !menu.contains(event.target) &&
            !button.contains(event.target)
        ){

            menu.style.display = "none";

        }

    });

</script>

</body>
</html>