
/**
 * @param {string} src - script link (URL)
 * @param {function} callback - callback function. Exec after script loading.
 */
function loadScript(src, callback = null) {
    let script = document.createElement('script');
    script.src = src;
    if (callback !== null) {
        script.onload = () => callback(script);
    }
    document.head.append(script);
}

/**
 * @param {string} src - script link (URL)
 *
 * usage:
 *      let promise = loadScript("https://cdnjs.cloudflare.com/ajax/libs/lodash.js/4.17.11/lodash.js");
 *
 *      promise.then(
 *          script => alert(`${script.src} загружен!`),
 *          error => alert(`Ошибка: ${error.message}`)
 *      );
 *
 *      promise.then(script => alert('Ещё один обработчик...'));
 */
function promiseScript(src) {
    return new Promise(function(resolve, reject) {
        let script = document.createElement('script');
        script.src = src;

        script.onload = () => resolve(script);
        script.onerror = () => reject(new Error(`Ошибка загрузки скрипта ${src}`));

        document.head.append(script);
    });
}


// возвращает куки с указанным name,
// или undefined, если ничего не найдено
function getCookie(name) {
    let matches = document.cookie.match(new RegExp(
        "(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
    ));
    return matches ? decodeURIComponent(matches[1]) : undefined;
}


/**
 * @param {string | number | boolean} name
 * @param {string | number | boolean} value
 * // Пример использования:
 * setCookie('user', 'John', {secure: true, 'max-age': 3600});
 */
function setCookie(name, value, options = {}) {

    options = {
        path: '/',
        // при необходимости добавьте другие значения по умолчанию
        ...options
    };

    if (options.expires instanceof Date) {
        options.expires = options.expires.toUTCString();
    }

    let updatedCookie = encodeURIComponent(name) + "=" + encodeURIComponent(value);

    for (let optionKey in options) {
        updatedCookie += "; " + optionKey;
        let optionValue = options[optionKey];
        if (optionValue !== true) {
            updatedCookie += "=" + optionValue;
        }
    }

    document.cookie = updatedCookie;
}


function deleteCookie(name) {
    setCookie(name, "", {
        'max-age': -1
    })
}