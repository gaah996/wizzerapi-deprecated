<html>
<head>
    <style>
        body {
            max-width: 600px;
            width: 100vw;
            margin: 10px auto;
            border: 30px solid #E5E5E5;
            padding: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: sans-serif;
            font-weight: 300;
            color: #3D3E40;
        }
        .logo img {
            height: 50px;
        }
        .greeting {
            width: 100%;
            display: flex;
        }
        .greeting h1 {
            font-size: 18px;
            padding: 80px 0 30px;
            font-weight: regular;
        }
        .content {
            width: 100%;
            display: flex;
            flex-direction: column;
            padding-bottom: 80px;
        }
        .content p {
            font-size: 14px;
            padding: 0;
            margin: 0;
            line-height: 1.5;
        }
        .content a {
            color: #0055ff;
        }
        .footer {
            width: 100%;
        }
        .footer .signature {
            padding-bottom: 30px;
        }
        .footer .signature p {
            font-size: 14px;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        .social-links img {
            height: 30px;
            margin-right: 10px;
        }
        .info {
            max-width: 280px;
            padding-top: 30px;
        }
        .info p {
            font-size: 12px;
            margin: 0 0 10px;
            padding: 0;
            line-height: 1.5;
        }
        .info strong {
            font-weight: regular;
        }
        .info .email {
            color: #0055FF;
            font-weight: 700;
        }
        .signature {
            padding-top: 80px;
        }
        .signature p {
            font-weight: 300;
            font-size: 16px;
        }
    </style>
</head>
<body>
<div class="logo"><img src="{{asset('images/emails/logo.png')}}" alt="Wizzer"></div>
<div class="greeting">
    <h1>Olá, {{$name}}.</h1>
</div>
<div class="content">
    <p>Esqueceu sua senha? Não tem problema!</p>
    <p>Estamos aqui para te ajudar.</p>
    <br>
    <p>Clique no link e redefina a sua senha agora mesmo.</p>
    <p><a href="https://www.wizzer.com.br/recuperar-senha/{{$resetCode}}">Redefinir minha senha</a></p>
</div>
<div class="footer">
    <div class="signature">
        <p>Abraços,</p>
        <p>Equipe Wizzer Imóveis.</p>
    </div>
    <div class="social-links">
        <a href="https://www.facebook.com/wizzer.imoveis"><img src="{{asset('images/emails/facebook.png')}}" alt="facebook"></a>
        <a href="https://www.instagram.com/wizzerimoveis"><img src="{{asset('images/emails/instagram.png')}}" alt="instagram"></a>
        <a href="https://www.linkedin.com/company/wizzer-im%C3%B3veis"><img src="{{asset('images/emails/linkedin.png')}}" alt="linkedin"></a>
    </div>
    <div class="info">
        <p><strong>Atendimento 24 horas</strong></p>
        <p>Em caso de qualquer dúvida, fique à vontade para nos contatar no <span class="email">suporte@wizzer.com.br</span>.</p>
    </div>
</div>
<div class="signature"><p>Wizzer 2019</p></div>
</body>
</html>