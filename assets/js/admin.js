/**
 * SMTP Connector admin screens: settings form helpers, confirmations and the email viewer.
 */
( function () {
    'use strict';

    var config = window.smtpConnectorAdmin || {};
    var i18n = config.i18n || {};

    function confirmBeforeSubmit( selector, message ) {
        document.querySelectorAll( selector ).forEach( function ( form ) {
            form.addEventListener( 'submit', function ( event ) {
                if ( ! window.confirm( message ) ) {
                    event.preventDefault();
                }
            } );
        } );
    }

    // Settings: show username/password only when authentication is on, suggest the usual port for
    // the chosen encryption, and fill in server details from the quick setup buttons.
    function initSettingsForm() {
        var auth = document.getElementById( 'smtp-connector-auth' );
        var host = document.getElementById( 'smtp-connector-host' );
        var port = document.getElementById( 'smtp-connector-port' );
        var username = document.getElementById( 'smtp-connector-username' );
        var hint = document.querySelector( '.smtp-connector-providers__hint' );
        var radios = document.querySelectorAll( 'input[name="smtp_connector_for_wp_security"]' );
        var defaultPorts = { tls: '587', ssl: '465', none: '25' };

        if ( auth ) {
            var update = function () {
                document.querySelectorAll( '.smtp-connector-auth-field' ).forEach( function ( element ) {
                    element.hidden = ! auth.checked;
                } );
                document.querySelectorAll( '.smtp-connector-auth-off' ).forEach( function ( element ) {
                    element.hidden = auth.checked;
                } );
            };
            auth.addEventListener( 'change', update );
            update();
        }

        radios.forEach( function ( radio ) {
            radio.addEventListener( 'change', function () {
                if ( port && radio.checked && [ '', '25', '465', '587' ].indexOf( port.value ) !== -1 ) {
                    port.value = defaultPorts[ radio.value ];
                }
            } );
        } );

        document.querySelectorAll( '.smtp-connector-provider' ).forEach( function ( button ) {
            button.addEventListener( 'click', function () {
                document.querySelectorAll( '.smtp-connector-provider' ).forEach( function ( other ) {
                    other.setAttribute( 'aria-pressed', other === button ? 'true' : 'false' );
                } );
                if ( host ) {
                    host.value = button.getAttribute( 'data-host' );
                }
                if ( port ) {
                    port.value = button.getAttribute( 'data-port' );
                }
                radios.forEach( function ( radio ) {
                    radio.checked = radio.value === button.getAttribute( 'data-security' );
                } );
                if ( auth && ! auth.checked ) {
                    auth.checked = true;
                    auth.dispatchEvent( new Event( 'change' ) );
                }
                // SendGrid always uses the literal username "apikey".
                if ( username && 'sendgrid' === button.getAttribute( 'data-provider' ) && '' === username.value ) {
                    username.value = 'apikey';
                }
                if ( hint ) {
                    hint.textContent = button.getAttribute( 'data-hint' );
                    hint.hidden = false;
                }
            } );
        } );
    }

    // Email Log: shows one email in a dialog. HTML is displayed in a sandboxed iframe, so it can't
    // run scripts, submit forms or cover the admin screen.
    function initEmailViewer() {
        var modal = document.getElementById( 'smtp-email-modal' );
        if ( ! modal ) {
            return;
        }
        var title = document.getElementById( 'smtp-email-modal-title' );
        var meta = modal.querySelector( '.smtp-connector-modal__meta' );
        var body = document.getElementById( 'smtp-email-modal-body' );
        var closeButton = modal.querySelector( '.smtp-connector-modal__close' );
        var opener = null;

        function addMeta( label, value ) {
            if ( ! value ) {
                return;
            }
            var term = document.createElement( 'dt' );
            var detail = document.createElement( 'dd' );
            term.textContent = label;
            detail.textContent = value;
            meta.appendChild( term );
            meta.appendChild( detail );
        }

        function open( email ) {
            title.textContent = email.subject || i18n.noSubject;
            meta.textContent = '';
            addMeta( i18n.to, email.to );
            addMeta( i18n.date, email.date );
            addMeta( i18n.status, email.status );
            addMeta( i18n.error, email.error );

            body.textContent = '';
            if ( ! email.content ) {
                var empty = document.createElement( 'p' );
                empty.textContent = i18n.emptyMessage;
                body.appendChild( empty );
            } else if ( email.isHtml ) {
                var frame = document.createElement( 'iframe' );
                frame.setAttribute( 'sandbox', '' );
                frame.setAttribute( 'referrerpolicy', 'no-referrer' );
                frame.setAttribute( 'title', i18n.emailContent );
                frame.className = 'smtp-connector-modal__frame';
                // Only images from this site (no tracking pixels from third parties) and inline styles.
                frame.srcdoc = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data: ' +
                    window.location.origin + '; style-src \'unsafe-inline\'; font-src data:">' + email.content;
                body.appendChild( frame );
            } else {
                var text = document.createElement( 'pre' );
                text.className = 'smtp-connector-modal__text';
                text.textContent = email.content;
                body.appendChild( text );
            }

            modal.hidden = false;
            document.body.classList.add( 'smtp-connector-modal-open' );
            closeButton.focus();
        }

        function close() {
            modal.hidden = true;
            body.textContent = '';
            document.body.classList.remove( 'smtp-connector-modal-open' );
            if ( opener ) {
                opener.focus();
            }
        }

        function load( button ) {
            var label = button.textContent;
            var data = new FormData();
            data.append( 'action', 'smtp_connector_for_wp_fetch_email_content' );
            data.append( 'nonce', config.nonce );
            data.append( 'email_id', button.getAttribute( 'data-email-id' ) );

            opener = button;
            button.disabled = true;
            button.textContent = i18n.loading;

            window.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: data } )
                .then( function ( response ) {
                    return response.json();
                } )
                .then( function ( response ) {
                    if ( response && response.success ) {
                        open( response.data );
                    } else {
                        window.alert( ( response && response.data && response.data.message ) || i18n.loadError );
                    }
                } )
                .catch( function () {
                    window.alert( i18n.loadError );
                } )
                .then( function () {
                    button.disabled = false;
                    button.textContent = label;
                } );
        }

        document.addEventListener( 'click', function ( event ) {
            var button = event.target.closest( '.smtp-view-email' );
            if ( button ) {
                event.preventDefault();
                load( button );
                return;
            }
            if ( ! modal.hidden && event.target.closest( '[data-smtp-close]' ) ) {
                close();
            }
        } );

        document.addEventListener( 'keydown', function ( event ) {
            if ( modal.hidden ) {
                return;
            }
            if ( 'Escape' === event.key ) {
                close();
                return;
            }
            // Keep keyboard focus inside the dialog.
            if ( 'Tab' === event.key ) {
                var focusable = modal.querySelectorAll( 'button, iframe, [href], [tabindex]:not([tabindex="-1"])' );
                var first = focusable[ 0 ];
                var last = focusable[ focusable.length - 1 ];
                if ( event.shiftKey && document.activeElement === first ) {
                    event.preventDefault();
                    last.focus();
                } else if ( ! event.shiftKey && document.activeElement === last ) {
                    event.preventDefault();
                    first.focus();
                }
            }
        } );
    }

    function init() {
        initSettingsForm();
        initEmailViewer();
        confirmBeforeSubmit( '.smtp-connector-reset-form', i18n.confirmReset );
        confirmBeforeSubmit( '.smtp-connector-clear-form', i18n.confirmClear );
    }

    if ( 'loading' === document.readyState ) {
        document.addEventListener( 'DOMContentLoaded', init );
    } else {
        init();
    }
}() );
