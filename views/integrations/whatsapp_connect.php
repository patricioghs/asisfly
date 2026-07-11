<?php
$appId = (string) ($config['app_id'] ?? '');
$configId = (string) ($config['config_id'] ?? '');
$graphVersion = (string) ($config['graph_version'] ?? 'v20.0');
$redirectUri = (string) ($config['redirect_uri'] ?? '');
$webhookUrl = (string) ($config['webhook_url'] ?? '');
?>

<section class="panel launch-hero">
    <div>
        <span class="eyebrow">Conexion oficial WhatsApp</span>
        <h2>Conectar WhatsApp Business</h2>
        <p>Autoriza tu numero desde Meta. AsisFly recibira los datos tecnicos y creara la cuenta automaticamente.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?= url('/integrations/accounts') ?>"><i class="bi bi-arrow-left"></i> Volver</a>
</section>

<section class="panel mt-4">
    <div class="row g-4 align-items-start">
        <div class="col-lg-7">
            <span class="eyebrow">Paso unico para la empresa</span>
            <h3>Autoriza el numero que usara AsisFly</h3>
            <p class="text-secondary">Se abrira Meta/Facebook para seleccionar o registrar el WhatsApp de empresa. Cuando termines, esta pantalla guardara la cuenta conectada en AsisFly.</p>

            <div class="d-flex gap-2 flex-wrap mt-4">
                <button id="wa-connect-button" class="btn btn-primary btn-lg" type="button">
                    <i class="bi bi-whatsapp"></i> Conectar WhatsApp con Meta
                </button>
                <button id="wa-save-button" class="btn btn-outline-primary btn-lg" type="button" disabled>
                    <i class="bi bi-check2-circle"></i> Guardar conexion
                </button>
            </div>

            <form id="wa-result-form" method="post" action="<?= url('/integrations/whatsapp/embedded-result') ?>" class="d-none">
                <?= csrf_field() ?>
                <input type="hidden" name="signup_payload" id="wa-signup-payload">
                <input type="hidden" name="auth_code" id="wa-auth-code">
            </form>

            <div id="wa-status" class="alert alert-info mt-4 mb-0">
                Esperando autorizacion de Meta.
            </div>
        </div>
        <div class="col-lg-5">
            <div class="account-credential-summary is-ready">
                <div>
                    <span class="eyebrow">Configuracion administrada</span>
                    <strong>AsisFly oculta los datos tecnicos al cliente</strong>
                    <small>El token global, webhook y App ID se administran desde Superadmin.</small>
                </div>
                <dl>
                    <div><dt>Webhook</dt><dd><?= e($webhookUrl) ?></dd></div>
                    <div><dt>Redirect</dt><dd><?= e($redirectUri) ?></dd></div>
                    <div><dt>Graph</dt><dd><?= e($graphVersion) ?></dd></div>
                </dl>
            </div>
        </div>
    </div>
</section>

<script>
window.fbAsyncInit = function () {
    FB.init({
        appId: '<?= e($appId) ?>',
        autoLogAppEvents: true,
        xfbml: true,
        version: '<?= e($graphVersion) ?>'
    });
};

(function (d, s, id) {
    if (d.getElementById(id)) return;
    const js = d.createElement(s);
    js.id = id;
    js.src = 'https://connect.facebook.net/es_LA/sdk.js';
    const first = d.getElementsByTagName(s)[0];
    first.parentNode.insertBefore(js, first);
}(document, 'script', 'facebook-jssdk'));

const statusBox = document.getElementById('wa-status');
const payloadInput = document.getElementById('wa-signup-payload');
const codeInput = document.getElementById('wa-auth-code');
const saveButton = document.getElementById('wa-save-button');
let signupPayload = null;

function setStatus(message, type = 'info') {
    statusBox.className = 'alert alert-' + type + ' mt-4 mb-0';
    statusBox.textContent = message;
}

window.addEventListener('message', (event) => {
    if (!event.origin.endsWith('facebook.com')) {
        return;
    }

    try {
        const data = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
        if (!data || data.type !== 'WA_EMBEDDED_SIGNUP') {
            return;
        }

        signupPayload = data;
        payloadInput.value = JSON.stringify(data);
        saveButton.disabled = false;
        setStatus('Meta devolvio los datos del numero. Puedes guardar la conexion.', 'success');
    } catch (error) {
        setStatus('Meta respondio, pero AsisFly no pudo leer el resultado automaticamente.', 'warning');
    }
});

document.getElementById('wa-connect-button').addEventListener('click', () => {
    if (typeof FB === 'undefined') {
        setStatus('El SDK de Meta aun no esta listo. Espera unos segundos e intenta nuevamente.', 'warning');
        return;
    }

    setStatus('Abriendo autorizacion de Meta...', 'info');
    FB.login((response) => {
        if (response && response.authResponse && response.authResponse.code) {
            codeInput.value = response.authResponse.code;
            setStatus(signupPayload ? 'Autorizacion recibida. Guarda la conexion.' : 'Autorizacion recibida. Esperando datos del numero...', 'success');
            saveButton.disabled = false;
            return;
        }

        setStatus('La autorizacion fue cancelada o no entrego codigo.', 'warning');
    }, {
        config_id: '<?= e($configId) ?>',
        response_type: 'code',
        override_default_response_type: true,
        extras: {
            setup: {},
            featureType: 'whatsapp_embedded_signup'
        }
    });
});

saveButton.addEventListener('click', () => {
    if (!payloadInput.value && !codeInput.value) {
        setStatus('Primero autoriza WhatsApp con Meta.', 'warning');
        return;
    }

    document.getElementById('wa-result-form').submit();
});
</script>
