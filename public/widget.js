(function () {
  if (!window.wp || !window.wp.element) {
    return;
  }

  const { createElement, useMemo, useState } = window.wp.element;
  const rootEl = document.getElementById('caringpays-chat-root');

  if (!rootEl) {
    return;
  }

  const ENTRY_MODES = [
    { key: 'text', label: 'Text Chat' },
    { key: 'audio', label: 'Audio Recording' },
    { key: 'video', label: 'Video Capture' },
  ];

  const normalizeValue = (value) => String(value || '')
    .toLowerCase()
    .trim()
    .replace(/\s+/g, '_');

  const collectUtm = () => {
    const params = new URLSearchParams(window.location.search);
    const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    return keys.reduce((carry, key) => {
      carry[key] = normalizeValue(params.get(key));
      return carry;
    }, {});
  };

  function App() {
    const [entryMode, setEntryMode] = useState(window.CaringPaysChatConfig?.defaultEntryMode || 'text');
    const [stateLocation, setStateLocation] = useState('');
    const [qAge, setQAge] = useState('');
    const [qResidency, setQResidency] = useState('');
    const [consentAccepted, setConsentAccepted] = useState(false);
    const [onboardingComplete, setOnboardingComplete] = useState(false);
    const [sessionToken, setSessionToken] = useState('');
    const [message, setMessage] = useState('');
    const [response, setResponse] = useState('');
    const [isRecording, setIsRecording] = useState(false);
    const [error, setError] = useState('');

    const utm = useMemo(() => collectUtm(), []);

    const beginMediaMode = async () => {
      if (entryMode === 'audio') {
        await navigator.mediaDevices.getUserMedia({ audio: true });
      }

      if (entryMode === 'video') {
        await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
      }

      if (entryMode !== 'text') {
        setIsRecording(true);
      }
    };

    const finishOnboarding = async () => {
      setError('');

      if (!stateLocation || !qAge || !qResidency || !consentAccepted) {
        setError('Complete state/location, eligibility screening, and digital consent to continue.');
        return;
      }

      try {
        await beginMediaMode();

        const generatedToken = `cp_${Date.now()}`;
        const payload = {
          session_token: generatedToken,
          state: stateLocation,
          entry_point: entryMode,
          eligibility_answers: {
            age_over_18: qAge,
            state_resident: qResidency,
          },
          consent_accepted: consentAccepted,
          utm,
        };

        const result = await fetch(`${window.CaringPaysChatConfig.apiBase}/chat/start-session`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.CaringPaysChatConfig.nonce,
          },
          body: JSON.stringify(payload),
        });

        if (!result.ok) {
          throw new Error('Unable to start session.');
        }

        setSessionToken(generatedToken);
        setOnboardingComplete(true);
      } catch (err) {
        setError(err.message || 'Unable to start onboarding flow.');
      }
    };

    const sendMessage = async () => {
      setError('');
      setResponse('');

      try {
        const result = await fetch(`${window.CaringPaysChatConfig.apiBase}/chat/message`, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': window.CaringPaysChatConfig.nonce,
          },
          body: JSON.stringify({
            session_token: sessionToken,
            message,
            turn_index: 1,
          }),
        });

        const data = await result.json();
        if (!result.ok || !data?.ok) {
          throw new Error(data?.message || 'AI request blocked until onboarding is complete.');
        }

        setResponse(`Sent (${entryMode}): ${data.data.message}`);
      } catch (err) {
        setError(err.message || 'Failed to send message.');
      }
    };

    return createElement('div', { className: 'cp-chat-widget' },
      createElement('h3', null, 'CaringPays Chat Widget'),
      createElement('div', { className: 'cp-entry-modes' },
        ENTRY_MODES.map((mode) => createElement('button', {
          key: mode.key,
          type: 'button',
          className: entryMode === mode.key ? 'active' : '',
          onClick: () => setEntryMode(mode.key),
        }, mode.label))
      ),
      !onboardingComplete && createElement('div', { className: 'cp-onboarding' },
        createElement('h4', null, 'Onboarding (required before AI interaction)'),
        createElement('label', null, '1) State/Location'),
        createElement('input', {
          type: 'text',
          value: stateLocation,
          onChange: (e) => setStateLocation(e.target.value),
          placeholder: 'e.g. california',
        }),
        createElement('label', null, '2) Eligibility: Are you over 18?'),
        createElement('select', { value: qAge, onChange: (e) => setQAge(e.target.value) },
          createElement('option', { value: '' }, 'Select'),
          createElement('option', { value: 'yes' }, 'Yes'),
          createElement('option', { value: 'no' }, 'No')
        ),
        createElement('label', null, 'Eligibility: Are you a state resident?'),
        createElement('select', { value: qResidency, onChange: (e) => setQResidency(e.target.value) },
          createElement('option', { value: '' }, 'Select'),
          createElement('option', { value: 'yes' }, 'Yes'),
          createElement('option', { value: 'no' }, 'No')
        ),
        createElement('label', { className: 'cp-consent' },
          createElement('input', {
            type: 'checkbox',
            checked: consentAccepted,
            onChange: (e) => setConsentAccepted(e.target.checked),
          }),
          '3) I provide mandatory digital consent.'
        ),
        createElement('button', { type: 'button', onClick: finishOnboarding }, 'Complete Onboarding')
      ),
      onboardingComplete && createElement('div', { className: 'cp-chat-controls' },
        createElement('p', null, `Session active (${entryMode}${isRecording ? ', media initialized' : ''})`),
        createElement('textarea', {
          value: message,
          onChange: (e) => setMessage(e.target.value),
          placeholder: 'Ask your question...',
        }),
        createElement('button', { type: 'button', onClick: sendMessage }, 'Send')
      ),
      response && createElement('pre', null, response),
      error && createElement('p', { className: 'cp-error' }, error)
    );
  }

  if (window.wp.element.createRoot) {
    window.wp.element.createRoot(rootEl).render(createElement(App));
  } else {
    window.wp.element.render(createElement(App), rootEl);
  }
})();
