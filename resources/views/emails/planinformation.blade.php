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
    <h1>Olá, Suporte.</h1>
</div>
<div class="content">
    <p>O {{$user->name}} enviou uma mensagem solicitando um plano personalizado.</p>
    <br>
    <p>Informações do plano:</p>
    <p><strong>Número de anúncios: </strong> {{$advertsNumber}}</p>
    <p><strong>Valor do plano: </strong> {{$price}}</p>
    <p><strong>E-mail do usuário: </strong> {{$user->email}}</p>
</div>
<div class="footer">
</div>
<div class="signature"><p>Wizzer 2019</p></div>
</body>
</html>