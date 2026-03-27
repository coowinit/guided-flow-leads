(function () {
  const config = window.guidedFlowLeads || {};
  const launcher = document.getElementById('gflLauncher');
  const panel = document.getElementById('gflWindow');
  const closeBtn = document.getElementById('gflClose');
  const form = document.getElementById('gflForm');
  const input = document.getElementById('gflInput');
  const sendBtn = document.getElementById('gflSend');
  const restartBtn = document.getElementById('gflRestart');
  const messages = document.getElementById('gflMessages');
  const status = document.getElementById('gflStatus');
  const choicesWrap = document.getElementById('gflChoices');

  if (!launcher || !panel || !form || !input || !sendBtn || !restartBtn || !messages || !status || !choicesWrap) {
    return;
  }

  const sessionKey = 'gflSessionId';
  let sessionId = window.localStorage.getItem(sessionKey) || '';
  let currentStepId = '';
  let flowStarted = false;

  function setOpen(isOpen) {
    panel.hidden = !isOpen;
    launcher.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (isOpen && !input.disabled) {
      input.focus();
    }
  }

  function appendMessage(role, text) {
    if (!text) {
      return;
    }

    const item = document.createElement('div');
    item.className = 'gfl-msg gfl-msg--' + role;

    const name = document.createElement('div');
    name.className = 'gfl-msg__name';
    name.textContent = role === 'user' ? config.strings.you : config.strings.assistant;

    const bubble = document.createElement('div');
    bubble.className = 'gfl-msg__bubble';
    bubble.textContent = text;

    item.appendChild(name);
    item.appendChild(bubble);
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
  }

  function setSending(isSending, isRestarting) {
    const sending = !!isSending;
    const restarting = !!isRestarting;
    sendBtn.disabled = sending || restarting || input.disabled;
    restartBtn.disabled = sending || restarting;
    sendBtn.textContent = sending ? config.strings.sending : config.strings.send;
    restartBtn.textContent = restarting ? config.strings.restarting : config.strings.restart;
  }

  function updateInput(step) {
    currentStepId = step.step_id || '';
    const inputType = step.input && step.input.type ? step.input.type : 'input_text';

    if (inputType === 'complete') {
      input.disabled = true;
      input.placeholder = config.strings.flowComplete;
      setSending(false, false);
      return;
    }

    input.disabled = false;
    input.placeholder = (step.input && step.input.placeholder) || config.strings.input;
  }

  function renderChoices(options) {
    choicesWrap.innerHTML = '';

    if (!Array.isArray(options) || !options.length) {
      choicesWrap.hidden = true;
      return;
    }

    options.forEach(function (option) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'gfl-choice';
      button.textContent = option.label || option.value || '';
      button.addEventListener('click', function () {
        submitAnswer(option.value || option.label || '', option.label || option.value || '');
      });
      choicesWrap.appendChild(button);
    });

    choicesWrap.hidden = false;
  }

  function applyPayload(data, appendAssistant) {
    if (!data) {
      return;
    }

    if (appendAssistant !== false) {
      appendMessage('assistant', data.assistant_message || config.strings.flowStartError);
    }

    if (data.session_id) {
      sessionId = data.session_id;
      window.localStorage.setItem(sessionKey, sessionId);
    }

    updateInput(data);
    renderChoices(data.options || []);

    const meta = data.meta || {};
    if (meta.completed) {
      status.textContent = config.strings.flowComplete;
      choicesWrap.hidden = true;
      input.value = '';
      return;
    }

    if (meta.progress && meta.steps_count) {
      status.textContent = 'Step ' + meta.progress + ' / ' + meta.steps_count;
    } else {
      status.textContent = config.strings.status;
    }
  }

  async function startOrResumeFlow() {
    if (flowStarted) {
      return;
    }

    flowStarted = true;
    setSending(true, false);
    status.textContent = config.strings.status;

    try {
      let response;

      if (sessionId) {
        const resumeUrl = new URL(config.flowResumeUrl, window.location.origin);
        resumeUrl.searchParams.set('session_id', sessionId);
        response = await fetch(resumeUrl.toString(), { method: 'GET' });
      } else {
        response = await fetch(config.flowStartUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            page_title: config.pageTitle || document.title,
            page_url: config.pageUrl || window.location.href,
          }),
        });
      }

      const data = await response.json();
      if (!response.ok || !data.success) {
        if (sessionId) {
          sessionId = '';
          window.localStorage.removeItem(sessionKey);
          flowStarted = false;
          return startOrResumeFlow();
        }
        throw new Error((data && data.message) || config.strings.flowStartError);
      }

      applyPayload(data, messages.childElementCount === 0);
    } catch (error) {
      flowStarted = false;
      appendMessage('assistant', error.message || config.strings.flowStartError);
    } finally {
      setSending(false, false);
    }
  }

  async function submitAnswer(answerValue, displayValue) {
    if (!currentStepId || !answerValue) {
      status.textContent = config.strings.empty;
      return;
    }

    appendMessage('user', displayValue || answerValue);
    input.value = '';
    setSending(true, false);

    try {
      const response = await fetch(config.flowNextUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          step_id: currentStepId,
          answer: answerValue,
          page_title: config.pageTitle || document.title,
          page_url: config.pageUrl || window.location.href,
        }),
      });

      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error((data && data.message) || config.strings.error);
      }

      applyPayload(data, true);
    } catch (error) {
      appendMessage('assistant', error.message || config.strings.error);
    } finally {
      setSending(false, false);
    }
  }

  async function restartFlow() {
    setSending(false, true);
    status.textContent = config.strings.restarting;

    try {
      const response = await fetch(config.flowRestartUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          session_id: sessionId,
          page_title: config.pageTitle || document.title,
          page_url: config.pageUrl || window.location.href,
        }),
      });

      const data = await response.json();
      if (!response.ok || !data.success) {
        throw new Error((data && data.message) || config.strings.error);
      }

      messages.innerHTML = '';
      choicesWrap.innerHTML = '';
      input.value = '';
      flowStarted = true;
      applyPayload(data, true);
    } catch (error) {
      appendMessage('assistant', error.message || config.strings.error);
    } finally {
      setSending(false, false);
    }
  }

  launcher.addEventListener('click', function () {
    const shouldOpen = panel.hidden;
    setOpen(shouldOpen);
    if (shouldOpen) {
      startOrResumeFlow();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      setOpen(false);
    });
  }

  restartBtn.addEventListener('click', function () {
    restartFlow();
  });

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const text = input.value.trim();
    if (!text) {
      status.textContent = config.strings.empty;
      input.focus();
      return;
    }
    submitAnswer(text, text);
  });
})();
