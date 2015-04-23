var TENGINE_CLIENT = TENGINE_CLIENT || {};

TENGINE_CLIENT.paramValidation = (function () {
    var __CLASS__ = function (UIElmt, callbacks) {
        this.UIElmt = $(UIElmt);
        this.callbacks = callbacks;
    };

    __CLASS__.prototype = {
        'UIElmt': null,
        'callbacks': {},
        'state': null,
        'started': false,
        'aborted': false,
        'info': null,
        'jqXHR': null,
        'timer': null,

        'reset': function () {
            this.state = 'testConnect';
            this.started = false;
            this.info = null;
            this.aborted = false;
            this.jqXHR = null;
            this.timer = null;
            this.clearUIElmt();
        },

        /**
         * Display message in current step.
         * @param msg
         * @param ok
         */
        'display': function (msg, ok) {
            var elmtId = 'pv-' + this.state;
            var elmt = document.getElementById(elmtId);
            var color = "";
            if (ok === true) {
                color = "pv-ok";
            } else if (ok === false) {
                color = "pv-ko"
            }
            if (elmt) {
                $(elmt).find('.pv-msg').first().text(this.escape(msg));
                if (color != '') {
                    $(elmt).find('.pv-msg').addClass(color);
                    $(elmt).find('.throbber').removeClass('running');
                } else {
                    $(elmt).find('.throbber').addClass('running');
                }
            } else {
                alert(msg);
            }
        },

        /**
         * Remove previous messages and ok/ko classes from
         * the UI element.
         */
        'clearUIElmt': function () {
            $(this.UIElmt).find('.pv-msg').text('').removeClass('pv-ok').removeClass('pv-ko');
        },

        'log': function (msg) {
            if (typeof console == 'object') {
                console.log(msg);
            }
        },

        'escape': function (msg) {
            return $('<div/>').text(msg).html();
        },

        'getUrl': function (data) {
            var args = '';
            for (var key in data) {
                if (data.hasOwnProperty(key)) {
                    args = args + '&' + key + '=' + data[key];
                }
            }
            return '?app=TENGINE_CLIENT&action=TENGINE_CLIENT_PARAMS&' + args;
        },

        /**
         * Run 'pre' callbacks.
         */
        'pre': function () {
            this.started = true;
            if (this.callbacks.hasOwnProperty('pre') && typeof this.callbacks['pre'] === 'function') {
                var args = [];
                if (this.callbacks.hasOwnProperty('pre-args')) {
                    args = this.callbacks['pre-args'];
                }
                this.callbacks['pre'].apply(this, args);
            }
        },

        /**
         * Run 'post' callbacks.
         */
        'post': function () {
            if (this.callbacks.hasOwnProperty('post') && typeof this.callbacks['post'] === 'function') {
                var args = [];
                if (this.callbacks.hasOwnProperty('post-args')) {
                    args = this.callbacks['post-args'];
                }
                this.callbacks['post'].apply(this, args);
            }
            this.started = false;
        },

        /**
         * Abort execution of param validation.
         */
        'abort': function () {
            this.display('Abort requested', false);
            this.aborted = true;
            if (this.timer) {
                window.clearTimeout(this.timer);
            }
            this.jqXHR.abort();
            this.post();
        },

        'isRunning': function () {
            return this.started;
        },

        /**
         * Main entry point for starting the param validation.
         * @param delay
         * @returns {*}
         */
        'run': function (delay) {
            if (this.aborted) {
                return;
            }
            if (!this.started) {
                this.reset();
                this.pre();
            }
            if (typeof delay == 'number') {
                var _this = this;
                this.timer = window.setTimeout(function () {
                    _this.run();
                }, delay);
                return;
            }
            switch (this.state) {
                case 'testConnect':
                    return this.testConnect();
                    break;
                case 'newTask':
                    return this.newTask();
                    break;
                case 'waitForTaskDone':
                    return this.waitForTaskDone();
                    break;
                case 'waitForCallback':
                    return this.waitForCallback();
                    break;
                case 'clearTask':
                    return this.clearTask();
                    break;
                case null:
                    if (this.callbacks.hasOwnProperty('post')) {
                        this.callbacks.post(this);
                    }
                    return this.post();
            }
            this.display("Unknown state '" + this.state + "'.", false);
        },

        /**
         * jQuery().ajax() wrapper
         * @param args
         * @param callbacks
         */
        'ajax': function (args, callbacks) {
            if (typeof callbacks != 'object') {
                callbacks = {};
            }
            var opts = {
                'url': this.getUrl(args),
                'context': this,
                'success': function (data, textStatus, jqXHR) {
                    if (typeof data != 'object') {
                        this.display("Error: data is not an object.", false);
                        return this.post();
                    }
                    if (!data.hasOwnProperty('success')) {
                        this.display("Error: missing 'success' property on data object.", false);
                        return this.post();
                    }
                    if (!data.success) {
                        if (callbacks.hasOwnProperty('error') && typeof callbacks['error'] === 'function') {
                            callbacks['error'].apply(this, [data, textStatus, jqXHR]);
                        } else {
                            this.display(data.message, false);
                        }
                        return this.post();
                    }
                    if (data.hasOwnProperty('next')) {
                        var delay = 2000;
                        if (this.state != data.next) {
                            delay = null;
                            this.display(data.message, true);
                        } else {
                            this.display(data.message);
                        }
                        if (callbacks.hasOwnProperty('success') && typeof callbacks['success'] === 'function') {
                            callbacks['success'].apply(this, [data, textStatus, jqXHR]);
                        }
                        this.state = data.next;
                        return this.run(delay);
                    }
                    if (callbacks.hasOwnProperty('success') && typeof callbacks['success'] === 'function') {
                        callbacks['success'].apply(this, [data, textStatus, jqXHR]);
                    }
                    return this.post();
                },
                'error': function (jqXHR, textStatus, errorThrown) {
                    if (callbacks.hasOwnProperty('error') && typeof callbacks['error'] === 'function') {
                        callbacks['error'].apply(this, [null, textStatus, jqXHR]);
                    } else {
                        this.display("Error: " + textStatus, false);
                    }
                    return this.post();
                }
            };
            this.display('');
            this.jqXHR = $.ajax(opts);
        },

        'testConnect': function () {
            this.ajax({
                'validate': 'testConnect'
            });
        },

        'newTask': function () {
            this.ajax(
                {
                    'validate': 'newTask'
                }, {
                    'success': function (data, textStatus, jqXHR) {
                        this.info = data.info;
                    }
                }
            );
        },

        'waitForTaskDone': function () {
            this.ajax({
                'validate': 'waitForTaskDone',
                'tid': this.info.tid
            });
        },

        'waitForCallback': function () {
            this.ajax({
                'validate': 'waitForCallback',
                'tid': this.info.tid
            });
        },

        'clearTask': function () {
            this.ajax({
                'validate': 'clearTask',
                'tid': this.info.tid
            });
        }
    };

    return __CLASS__;
})();

$(document).ready(function () {

    $('input, .ui-button').button();
    var buttons = "";
    $("select").each(function () {
        var rb = '', idrb = '';
        for (var i = 0; i < this.options.length; i++) {
            idrb = this.id + '_' + i;
            rb = $('<input type="radio" value="' + this.options[i].value + '" id="' + idrb + '" ' + ((this.options[i].selected) ? "checked" : "") + '  name="' + this.name + '" /><label for="' + idrb + '">' + this.options[i].label + '</label>');
            $(this).parent().append(rb);
        }

        rb = $('<input type="hidden" data-original="1" id="' + this.id + '"/>');
        $(this).parent().append(rb);
        $(this).remove();
    });

    $('input[type=radio]').parent().buttonset();

    $("body").on("MODPARAMETER", function (event, data) {
        if (data.success) {
            var pid = data.parameterid;
            if (data.data.modify) {
                $('#' + data.data.parameterid + ',#' + data.data.parameterid + '_0').parents('.editapplicationparameter').find('div[data-type=label] label').addClass('modified');

            }
        } else {
            alert(data.error);
        }
    });

    $("body").on("mouseup", "label.ui-button", function (event) {
        if (!$(this).hasClass('ui-state-active')) {
            var originValue = $('#' + $(this).attr('for')).val();
            $(this).parents('form').find('input[data-original=1]').val(originValue);
            sendParameterApplicationData(this);
        }
    });

    $("form").on("keypress", "input[type=text]", function (e) {
        var code = e.keyCode || e.which;
        if (code == 13) {
            e.preventDefault();
            sendParameterApplicationData(this);
            return false;
        }
    });

    $('#checkconnection').on('click', function () {
        if (window.paramValidation && window.paramValidation.isRunning()) {
            window.paramValidation.abort();
        } else {
            var button = this;
            window.paramValidation = new TENGINE_CLIENT.paramValidation(
                document.getElementById('param-validation'),
                {
                    'pre': function (button) {
                        if (!this.hasOwnProperty('_ui_button_text')) {
                            this._ui_button_text = $(button).find('.ui-button-text').first().text();
                        }
                        $(button).find('.ui-button-text').first().text('⬛ Stop');
                    },
                    'pre-args': [button],
                    'post': function (button) {
                        if (this.hasOwnProperty('_ui_button_text')) {
                            $(button).find('.ui-button-text').first().text(this._ui_button_text);
                        }
                    },
                    'post-args': [button]
                }
            );
            window.paramValidation.run();
        }
    });
});
