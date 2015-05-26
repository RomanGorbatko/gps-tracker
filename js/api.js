/**
 * Объект-обертка для работы с AJAX запросами.
 *
 * ВАЖНО!
 * Для работы с кросс-доменными приложениям (в нашем случае клиент(смартфон) имеет совершенно иной IP адрес, отличный от адреса сервере), 
 * нужно в объект конфигов для $.ajax добавить суб-объект xhrFields: {withCredentials: false}.
 */

var api = {
    connection: {
        baseUrl: 'http://web-master.ck.ua',
        apiUrl: '/timoshenko/json.php?f='
    },
    initialize: function() {
        api.setupAjaxDefaults();
    },
    setupAjaxDefaults: function() {
        var headers = {
            'Accept': "application/json; encoding='utf-8'",
            'Content-Type': "application/json; encoding='utf-8'"
        };
        $.ajaxSetup({
            headers: headers,
            dataType: 'json',
            crossDomain: true
        });
    },
    testCall: function(data) {
        api.ajaxGet( someMethod, data, aSuccessCallback, anErrorCallback );
    },
    ajaxGet: function(methodName, data, successCallback, errorCallback) {
        $.ajax({
            url: api.connection.baseUrl + api.connection.apiUrl + methodName,
            data: data,
            cache: false,
            type: 'GET',
            success: function(result, status, xhr) {
                if ($.isFunction(successCallback)) {
                    successCallback(result, status);
                }
            },
            error: function() {
                if ($.isFunction(errorCallback)) {
                    errorCallback(arguments);
                }
            }

        });
    },
    ajaxPost: function(methodName, data, successCallback, errorCallback) {
        $.ajax({
            url: api.connection.baseUrl + api.connection.apiUrl + methodName,
            data: data,
            cache: false,
            type: 'POST',
            xhrFields: {
                // The 'xhrFields' property sets additional fields on the XMLHttpRequest.
                // This can be used to set the 'withCredentials' property.
                // Set the value to 'true' if you'd like to pass cookies to the server.
                // If this is enabled, your server must respond with the header
                // 'Access-Control-Allow-Credentials: true'.
                withCredentials: false
            },
            success: function(result, status, xhr) {
                if ($.isFunction(successCallback)) {
                    successCallback(result, status);
                }
            },
            error: function() {
                if ($.isFunction(errorCallback)) {
                    errorCallback(arguments);
                }
            }

        });
    },
    ajaxGetCached: function(methodName, data, successCallback, errorCallback) {
        $.ajax({
            url: api.connection.baseUrl + api.connection.apiUrl + methodName,
            data: data,
            type: 'GET',
            success: function(result, status, xhr) {
                if ($.isFunction(successCallback)) {
                    successCallback(result);
                }
            },
            error: function() {
                if ($.isFunction(errorCallback)) {
                    errorCallback(arguments);
                }
            }

        });
    }
};