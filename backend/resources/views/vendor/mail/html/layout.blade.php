<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
/* Le media query non sono inlinabili da CssToInlineStyles: restano qui,
mentre la palette di base sta in `themes/default.css`. Stessi valori del
layout del reset password, cosi' le due famiglie di email non divergono. */
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}

.header {
padding: 18px 20px !important;
}

.content-cell {
padding: 24px 20px !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}

@media (prefers-color-scheme: dark) {
body, .wrapper, .body {
background-color: #08090d !important;
color: #e7eaee !important;
}

.inner-body {
background-color: #2e3548 !important;
border-color: #4f5972 !important;
box-shadow: none !important;
}

.header {
background-color: #1b2130 !important;
}

.header-mark {
background-color: #34507a !important;
}

p, .table td, .content-cell td {
color: #e7eaee !important;
}

.content-cell table, .content-cell th, .content-cell td {
border-color: #4f5972 !important;
}

.content-cell th {
background-color: #262c3c !important;
}

.content-cell td:first-child {
color: #b2b9c3 !important;
}

h1, h2, h3, strong, .table th, .content-cell th {
color: #ffffff !important;
}

a {
color: #9dbde8 !important;
}

/* Specificita' maggiore di `a`, altrimenti il colore del link vince
sull'etichetta del bottone e la rende illeggibile. */
a.button, .button, .button-blue, .button-primary {
background-color: #799ece !important;
border-color: #799ece !important;
color: #172230 !important;
}

.subcopy {
border-top-color: #4f5972 !important;
}

.subcopy p, .footer p, .footer a {
color: #b2b9c3 !important;
}

.panel-content, .panel-content p {
background-color: #262c3c !important;
color: #e7eaee !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
