<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Passkey Verification</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: #f5f5f5;
            margin: 0; padding: 0;
            min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
        }
        .container { width: 100%; max-width: 400px; padding: 1rem; }
        h1 { text-align: center; color: #333; font-size: 1.5rem; margin-bottom: 1rem; }
        p { text-align: center; color: #666; margin-bottom: 1.5rem; }
        button {
            width: 100%; padding: 0.75rem; background: #2563eb; color: #fff;
            border: none; border-radius: 0.375rem; font-size: 1rem; cursor: pointer;
        }
        button:hover { background: #1d4ed8; }
        .error { color: #dc2626; text-align: center; margin-top: 1rem; display: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Passkey Verification</h1>
        <p>Verify your identity with your passkey to continue.</p>
        <button id="verify-btn" onclick="verifyPasskey()">Verify with Passkey</button>
        <p class="error" id="error-msg"></p>
    </div>

    <script>
        async function verifyPasskey() {
            const btn = document.getElementById('verify-btn');
            const errorMsg = document.getElementById('error-msg');
            btn.disabled = true;
            errorMsg.style.display = 'none';

            try {
                const optionsRes = await fetch('{{ route('passkeys.2fa.options') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                if (! optionsRes.ok) throw new Error('Failed to get verification options.');

                const options = await optionsRes.json();

                options.challenge = base64urlToBuffer(options.challenge);
                if (options.allowCredentials) {
                    options.allowCredentials = options.allowCredentials.map(c => ({
                        ...c, id: base64urlToBuffer(c.id),
                    }));
                }

                const credential = await navigator.credentials.get({ publicKey: options });

                const verifyRes = await fetch('{{ route('passkeys.2fa.verify') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        credential: credentialToJson(credential),
                    }),
                });

                const result = await verifyRes.json();

                if (verifyRes.ok && result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    throw new Error(result.message || 'Verification failed.');
                }
            } catch (err) {
                errorMsg.textContent = err.message;
                errorMsg.style.display = 'block';
                btn.disabled = false;
            }
        }

        function base64urlToBuffer(base64url) {
            const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
            const bin = atob(base64);
            return Uint8Array.from(bin, c => c.charCodeAt(0)).buffer;
        }

        function bufferToBase64url(buffer) {
            const bytes = new Uint8Array(buffer);
            let str = '';
            bytes.forEach(b => str += String.fromCharCode(b));
            return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
        }

        function credentialToJson(credential) {
            return {
                id: credential.id,
                rawId: bufferToBase64url(credential.rawId),
                type: credential.type,
                response: {
                    authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                    clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                    signature: bufferToBase64url(credential.response.signature),
                    userHandle: credential.response.userHandle
                        ? bufferToBase64url(credential.response.userHandle)
                        : null,
                },
            };
        }
    </script>
</body>
</html>
