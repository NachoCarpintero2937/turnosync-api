<html>

<head></head>

<body>
    <table class="m_4980895120330400271wrapper"
        style="border-collapse:collapse;table-layout:fixed;min-width:320px;width:100%;background-color:#eeeeee;"
        cellpadding="0" cellspacing="0" role="presentation">
        <tbody>
            <tr>
                <td>
                    <div role="banner">
                        <div style="line-height:10px;font-size:1px">&nbsp;</div>
                        <div class="m_4980895120330400271layout m_4980895120330400271one-col"
                            style="Margin:0 auto;max-width:600px;min-width:320px;width:320px;width:calc(28000% - 167400px);word-wrap:break-word;word-break:break-word;border-bottom:1px solid #cccccc">
                            <div class="m_4980895120330400271layout__inner"
                                style="border-collapse:collapse;display:table;width:100%;/* background-color:#ffffff */">
                                <div class="m_4980895120330400271column"
                                    style="max-width:600px;min-width:320px;width:320px;width:calc(28000% - 167400px)">
                                    <div style="Margin-left:20px;Margin-right:20px;Margin-top:20px;Margin-bottom:20px">
                                        <center>
                                            <table style="border-collapse:collapse;table-layout:fixed">
                                                <tbody>
                                                    <tr>
                                                        <td>
                                                            <table cellpadding="0" cellspacing="0" border="0">
                                                                <tbody>
                                                                    <tr>
                                                                        <td width="800" style="text-align: center">
                                                                            <a href="#"
                                                                                style="text-decoration:none;color:{{$mailData['configurations']['toolbar']}};font-size:30px"
                                                                                target="_blank">
                                                                                <span style="">{{$mailData['companyName']}}</span>

                                                                            </a>
                                                                        </td>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </center>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div role="section">
                        <div style="width:100%;max-width:850px;background-color:#eeeeee;margin-left:auto;margin-right:auto">
                            <div style="margin:auto;padding-top:10px;padding-bottom:15px;max-width:600px;min-width:320px;width:320px;width:calc(28000% - 167400px);word-wrap:break-word;word-break:break-word;height:13px;font-family:Arial;font-size:11px;font-weight:normal;font-style:normal;font-stretch:normal;line-height:normal;letter-spacing:normal;text-align:center;color:#444444;">
                            </div>
                            <div style="padding-top:20px;background-color:#ffffff;max-width:600px;min-width:320px;width:320px;width:calc(28000% - 167400px);word-wrap:break-word;word-break:break-word;margin-right:auto;margin-left:auto;margin-bottom:0px">
                                <div style="margin:auto;border-collapse:collapse;display:table;width:100%;height:27px;font-family:Arial;font-size:24px;font-weight:normal;font-style:normal;font-stretch:normal;line-height:normal;letter-spacing:normal;text-align:center;    color: #6a6a6a;font-weight: 700;paddin:5px;">
                                    <span>¡Hola {{ $mailData['clientName'] }}!</span>
                                </div>
                                <div style="margin-right:auto;margin-left:auto;margin-top:25px;border-collapse:collapse;width:90%;width:559px;height:0px;border:solid 1px #dddddd">
                                </div>
                            </div>
                            <div style="background-color:#ffffff;margin-left:auto;margin-right:auto;padding-top:22px;padding-bottom:15px;text-align:center;max-width:70%;min-width:320px;width:320px;width:calc(28000% - 167400px);word-wrap:break-word;word-break:break-word;font-family:Arial;font-size:16px;font-weight:normal;font-style:normal;font-stretch:normal;line-height:1.25;letter-spacing:normal;color:#444444">
                                <div style="border-collapse:collapse;display:table;width:100%;max-width:550px;margin:auto">
                                    <p></p>
                                    <p><b>¡Hemos registrado un turno a tu nombre para {{ $mailData['serviceName'] }} !</b></p>
                                    <p></p>
                                    <p>Te esperamos el día <b>{{ \Carbon\Carbon::parse($mailData['shiftDate'])->format('d/m/Y H:i') }} </b></p>
                                    @if($mailData['serviceName'] == 'Depilación definitiva')
                                    <b>Por favor NO te olvides la toalla.</b>
                                    @endif
                                    <p>¡Gracias!</p>
                                    <div style="margin-right:auto;margin-left:auto;margin-top:25px;border-collapse:collapse;width:90%;width:559px;height:0px;border:solid 1px #dddddd">
                                    </div>
                                    <p><b>No hace falta que imprimas este email</b></p>
                                    <p style=" font-size: 11px;
                              "></p>
                                </div>
                            </div>
                            <div style="margin:auto;max-width:600px;min-width:320px;width:320px;width:calc(28000% - 167400px);word-wrap:break-word;word-break:break-word;background-color: {{$mailData['configurations']['toolbar']}};padding-top:25px;padding-bottom:20px">
                                <div style="margin:auto;border-collapse:collapse;display:table;width:100%;height:20px;font-family:Arial;font-size:16px;font-weight:normal;font-style:normal;font-stretch:normal;line-height:1.25;letter-spacing:normal;text-align:center;color:#ffffff">
                                    <span>Nuestra dirección es : <br>
                                        {{$mailData['address']}}
                                        {{-- Para abrir google maps presione <a href="https://www.google.com.ar/maps/place/Mercedes+961,+B1712JBA+Castelar,+Provincia+de+Buenos+Aires"
                                         style="font-family:Arial;font-size:16px;font-weight:normal;font-style:normal;font-stretch:normal;line-height:1.25;letter-spacing:normal;text-align:center;color:#ffff" 
                                         target="_blank">Aquí

                                        </a></span> --}}
                                </div>
                                <div style="margin-left:auto;margin-right:auto;margin-top:25px;border-collapse:collapse;display:table;width:100%;height:0px;border:solid 0.5px #5c8fe1">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div role="contentinfo">
                        <div style="line-height:18px;font-size:18px">&nbsp;</div>
                        <div class="m_4980895120330400271layout m_4980895120330400271one-col m_4980895120330400271fixed-width"
                            style="Margin:0 auto;max-width:600px;min-width:320px;width:320px;width:calc(28000% - 167400px);word-wrap:break-word;word-break:break-word;background-color:#ffffff">
                        </div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</body>

</html>