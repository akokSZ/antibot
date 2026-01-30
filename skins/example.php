<?
$nonce = \Utility\GenerateRandomName::genKey(17);
$funcName = \Utility\GenerateRandomName::genFuncName();
$tagCheckbox = Utility\GenerateRandomName::genFuncName(4, 6);
?><html lang="en-US" dir="ltr">

<head>
    <meta http-equiv="x-ua-compatible" content="IE=Edge,chrome=1">
    <meta http-equiv="content-security-policy"
        content="default-src 'none'; script-src 'nonce-<?= $nonce; ?>' 'unsafe-eval'; script-src-attr 'none'; worker-src blob:; style-src 'unsafe-inline'; img-src 'self'; connect-src 'self'; frame-src 'self' blob:; child-src 'self' blob:; form-action 'none'; base-uri 'self'">
    <title>Checking your Browser…</title>
    <meta name="robots" content="noindex,nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <style>
        @media (prefers-color-scheme: dark) {
            body {
                color: #fff;
            }
        }
    </style>
</head>

<body>
    <label><input type="checkbox" id="<?= $tagCheckbox ?>">Подтвердите, что вы человек</label>
    <script type="text/javascript" nonce="<?= $nonce ?>">
        let CSRF = "<?= $_REQUEST["csrf"] ?>";
        var HTTP_ANTIBOT_PATH = '<?= $antiBot->Config->ANTIBOT_PATH; ?>';

        const input = document.getElementById("<?= $tagCheckbox ?>");
        input.addEventListener('click', function(event) {
            if (this.checked) {
                <?= $funcName ?>('<?= $antiBot->Marker->getNameMarker() ?>');
            }
        });

        function <?= $funcName ?>(func) {
            var xhr = new XMLHttpRequest();
            var visitortime = new Date();

            let obj = {
                func: func == undefined ? 'csrf_token' : func,
                csrf_token: CSRF,
                mainFrame: window.top === window.self,
            };

            let data = null;
            try {
                data = JSON.stringify(obj);
            } catch (e) {
                console.error('Failed to stringify data:', e);
            }

            xhr.open('POST', HTTP_ANTIBOT_PATH + 'xhr.php', true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onload = async function() {
                if (xhr.status >= 200 && xhr.status < 300) {
                    var data = JSON.parse(xhr.responseText);

                    CSRF = data.csrf_token;
                    if (CSRF == undefined || CSRF == '') {
                        console.log('Error getting csrf_token');
                        return;
                    }
                    // Перезагружает капчу
                    if (data.status == 'captcha') {
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('csrf', CSRF);
                        window.location.href = currentUrl.toString();
                    } 
                    // Успешно прошел капчу
                    else if (data.status == 'allow') {
                        parent.allow();
                    } 
                    // НЕ прошел капчу, пользователь заблокирован
                    else if (data.status == 'block') {
                        setTimeout(parent.block, 1000);
                    } 
                    // Технические ошибки, когда требуется вмешательство тех. специалиста
                    else if (data.status == 'fail') {} 
                    else {
                        console.log(data);
                    }
                }
            };
            xhr.onerror = function() {
                console.error('Network error occurred');
            };
            xhr.send(data);
        }
    </script>
</body>

</html>