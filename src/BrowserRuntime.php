<?php

declare(strict_types=1);

namespace AML\View;

final class BrowserRuntime
{
    public static function script(string $endpoint): string
    {
        $url = json_encode($endpoint, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return <<<HTML
<script>
(() => {
  const endpoint = {$url};
  const hydrate = (root = document) => {
    root.querySelectorAll('[data-aml-value]').forEach((control) => {
      if (control.tagName === 'SELECT' || control.tagName === 'TEXTAREA') {
        control.value = control.dataset.amlValue;
      }
    });
  };
  const interact = async (target, eventName, data = {}) => {
    if (!target) return;
    const root = target.closest('[data-aml-root]');
    if (!root) return;
    target.disabled = true;
    const previousLabel = target.innerHTML;
    if (target.dataset.amlLoadingLabel) target.textContent = target.dataset.amlLoadingLabel;
    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-AML-View': 'interaction'},
        body: JSON.stringify({event: target.dataset['aml' + eventName], token: root.dataset.amlToken, data})
      });
      if (!response.ok) throw new Error(`AML View interaction failed: \${response.status}`);
      const result = await response.json();
      root.innerHTML = result.html;
      root.dataset.amlToken = result.token;
      hydrate(root);
    } finally {
      if (target.isConnected) {
        target.disabled = false;
        target.innerHTML = previousLabel;
      }
    }
  };
  document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-aml-click]');
    if (!target) return;
    event.preventDefault();
    interact(target, 'Click');
  });
  document.addEventListener('submit', (event) => {
    const target = event.target.closest('[data-aml-submit]');
    if (!target) return;
    event.preventDefault();
    interact(target, 'Submit', Object.fromEntries(new FormData(target).entries()));
  });
  document.addEventListener('change', (event) => {
    const target = event.target.closest('[data-aml-change]');
    if (!target) return;
    interact(target, 'Change', {
      value: target.type === 'checkbox' ? target.checked : target.value,
      name: target.name || ''
    });
  });
  const inputTimers = new WeakMap();
  document.addEventListener('input', (event) => {
    const target = event.target.closest('[data-aml-input]');
    if (!target) return;
    clearTimeout(inputTimers.get(target));
    inputTimers.set(target, setTimeout(() => interact(target, 'Input', {
      value: target.value,
      name: target.name || ''
    }), 250));
  });
  hydrate();
})();
</script>
HTML;
    }
}
