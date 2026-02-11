let fLoad = false; // защита от зацикливания
function loadLangScript(pathFile, callback = null) {
    var script = document.createElement('script');
    script.src = HTTP_ANTIBOT_PATH + pathFile;
    script.async = true;
    if (callback !== null) script.onload = callback;
    script.onerror = function () {
        if(!fLoad) {
            loadLangScript('js/lang/en.js', callbackLanguage);
            fLoad = true;
        } else {
            console.log("Error: Not load language script js/lang/ru.js");
        }
    };
    document.head.appendChild(script);
}

// Загрузился языковой модуль
function callbackLanguage() {
    document.title = (document.title == "Требуется внимание!") // костыль
        ? document.title = lang['block_title'] : document.title = lang['title'];
    document.querySelectorAll('[data-translate]').forEach(translateElement);
}

// Функция для перевода текста
function translateElement(element) {
    const key = element.getAttribute('data-translate');
    if (key && lang[key]) {
        let text = lang[key];

        // Замена {domain} и других переменных
        const domain = window.location.hostname;
        text = text.replace(/{domain}/g, domain);

        element.innerHTML = text;
    }
}

(function () {
    const langCode = (navigator.language || navigator.userLanguage).substring(0, 2);
    loadLangScript('js/lang/' + langCode + '.js', callbackLanguage);
}());