(() => {
  const escapeHtml = value => value.replace(/[&<>]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[character]));

  const bridgeRequest = (target, body, headers = {}) => new Promise((resolve, reject) => {
    const id = crypto.randomUUID();
    console.info('[OmnInterface App] 🚀 Début bridgeRequest', { id, target, headers, body });
    
    // Timeout augmenté à 120 secondes pour laisser le temps au modèle LLM de répondre
    const timer = setTimeout(() => {
      window.removeEventListener('message', listener);
      console.error('[OmnInterface App] ❌ TIMEOUT (120s) sur bridgeRequest', { id, target });
      reject(new Error('OmniRoute n’a pas répondu dans le délai de 120s.'));
    }, 120000);

    const listener = event => {
      if (event.source !== window || event.data?.channel !== 'omninterface' || event.data.type !== 'response' || event.data.id !== id) return;
      clearTimeout(timer);
      window.removeEventListener('message', listener);
      
      console.info('[OmnInterface App] 📩 Réponse reçue du bridge', event.data);
      const response = event.data.response;
      if (!response?.ok) {
        console.error('[OmnInterface App] ❌ Erreur renvoyée par le bridge :', response);
        reject(new Error(response?.error || `OmniRoute a répondu HTTP ${response?.status || 0}.`));
      } else {
        console.info('[OmnInterface App] ✅ Réponse locale valide !', response.body);
        resolve(response.body);
      }
    };

    window.addEventListener('message', listener);
    console.info('[OmnInterface App] 📤 Émission de postMessage (type: request)', { id });
    window.postMessage({ channel: 'omninterface', type: 'request', id, target, method: 'POST', headers, body }, window.location.origin);
  });

  const bridgePing = () => new Promise((resolve, reject) => {
    const id = crypto.randomUUID();
    console.info('[OmnInterface App] 🔍 Début bridgePing', { id });
    
    const timer = setTimeout(() => {
      window.removeEventListener('message', listener);
      console.error('[OmnInterface App] ❌ TIMEOUT ping (5s) - Extension absente');
      reject(new Error('Extension OmnInterface Unblock absente ou inactive. Vérifiez que la version est bien rechargée.'));
    }, 5000);

    const listener = event => {
      if (event.source !== window || event.data?.channel !== 'omninterface' || event.data.type !== 'pong' || event.data.id !== id) return;
      clearTimeout(timer);
      window.removeEventListener('message', listener);
      
      console.info('[OmnInterface App] 🟢 Pong reçu !', event.data);
      if (!event.data.response?.ok) {
        console.error('[OmnInterface App] ❌ Erreur lors du pong :', event.data.response);
        reject(new Error(event.data.response?.error || 'Service worker inaccessible.'));
      } else {
        resolve(event.data.response);
      }
    };

    window.addEventListener('message', listener);
    console.info('[OmnInterface App] 📤 Émission de postMessage (type: ping)', { id });
    window.postMessage({ channel: 'omninterface', type: 'ping', id }, window.location.origin);
  });

  const passkey = async (button, form) => {
    button.disabled = true;
    try {
      const credential = await (button.id === 'create-passkey'
        ? navigator.credentials.create({ publicKey: { challenge: crypto.getRandomValues(new Uint8Array(32)), rp: { name: 'OmnInterface', id: location.hostname }, user: { id: crypto.getRandomValues(new Uint8Array(16)), name: 'omninterface', displayName: 'OmnInterface' }, pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }], authenticatorSelection: { residentKey: 'required', userVerification: 'preferred' }, timeout: 60000 } })
        : navigator.credentials.get({ publicKey: { challenge: crypto.getRandomValues(new Uint8Array(32)), rpId: location.hostname, userVerification: 'preferred', timeout: 60000 } }));
      if (!credential) throw new Error('Aucune clé sélectionnée.');
      form.querySelector('#credential_id').value = btoa(String.fromCharCode(...new Uint8Array(credential.rawId))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
      form.submit();
    } catch (error) {
      button.disabled = false;
      alert(error.message || 'La création de la passkey a été annulée.');
    }
  };

  document.querySelector('#create-passkey')?.addEventListener('click', event => passkey(event.currentTarget, document.querySelector('#passkey-form')));
  document.querySelector('#use-passkey')?.addEventListener('click', event => passkey(event.currentTarget, document.querySelector('#login-passkey-form')));
  document.querySelector('#new-chat')?.addEventListener('click', async () => {
    const response = await fetch('api.php?action=new', { method: 'POST', headers: { 'X-CSRF-Token': window.omni.csrf } });
    const data = await response.json();
    location.href = `chat.php?conversation=${data.id}`;
  });

  const composer = document.querySelector('#composer');
  composer?.addEventListener('submit', async event => {
    event.preventDefault();
    const input = composer.querySelector('textarea');
    const text = input.value.trim();
    if (!text) return;
    const messages = document.querySelector('#messages');
    messages.querySelector('.empty-state')?.remove();
    messages.insertAdjacentHTML('beforeend', `<article class="message user"><div class="avatar">Vous</div><div class="message-content">${escapeHtml(text)}</div></article><article class="message assistant is-thinking"><div class="avatar">✦</div><div class="message-content"><span class="typing"></span><span class="typing"></span><span class="typing"></span></div></article>`);
    input.value = '';
    input.style.height = 'auto';
    messages.scrollTop = messages.scrollHeight;

    try {
      let data;
      if (window.omni.localBridge) {
        console.info('[OmnInterface App] 🔗 Mode pont local activé.');
        await bridgePing();
        
        const targetUrl = window.omni.omnirouteUrl.replace(/\/+$/, '') + '/chat/completions';
        const headers = {};
        if (window.omni.omnirouteApiKey) {
          headers['Authorization'] = 'Bearer ' + window.omni.omnirouteApiKey;
        }

        const result = await bridgeRequest(
          targetUrl,
          { model: 'auto', messages: [...window.omni.history, { role: 'user', content: text }], stream: false },
          headers
        );

        const reply = result?.choices?.[0]?.message?.content;
        if (!reply) throw new Error('Réponse OmniRoute locale inattendue.');

        const saved = await fetch('api.php?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.omni.csrf }, body: JSON.stringify({ conversation_id: window.omni.conversation, user_content: text, assistant_content: reply }) });
        data = await saved.json();
        if (!saved.ok) throw new Error(data.error || 'Impossible de sauvegarder la discussion.');
        window.omni.history.push({ role: 'user', content: text }, { role: 'assistant', content: reply });
      } else {
        console.info('[OmnInterface App] 🌐 Mode serveur distant classique.');
        const response = await fetch('api.php?action=send', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.omni.csrf }, body: JSON.stringify({ content: text, conversation_id: window.omni.conversation }) });
        data = await response.json();
        if (!response.ok) throw new Error(data.error);
      }

      if (data.conversation_id !== window.omni.conversation) location.href = `chat.php?conversation=${data.conversation_id}`;
      else document.querySelector('.is-thinking').outerHTML = `<article class="message assistant"><div class="avatar">✦</div><div class="message-content">${escapeHtml(data.reply).replace(/\n/g, '<br>')}</div></article>`;
    } catch (error) {
      console.error('[OmnInterface App] 💥 Erreur globale lors de l’envoi :', error);
      document.querySelector('.is-thinking')?.remove();
      alert(error.message);
    }
    messages.scrollTop = messages.scrollHeight;
  });

  document.querySelector('textarea')?.addEventListener('input', event => {
    event.currentTarget.style.height = 'auto';
    event.currentTarget.style.height = `${Math.min(event.currentTarget.scrollHeight, 160)}px`;
  });
  document.querySelector('#mobile-menu')?.addEventListener('click', () => document.querySelector('.sidebar')?.classList.toggle('is-open'));
  document.querySelectorAll('.suggestions button').forEach(button => button.addEventListener('click', () => {
    const input = document.querySelector('textarea');
    input.value = button.textContent;
    input.focus();
  }));
})();