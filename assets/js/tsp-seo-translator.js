(function () {
  function loadGoogleTranslateScript() {
    if (document.getElementById('google_translate_script')) return;

    var script = document.createElement('script');
    script.id = 'google_translate_script';
    script.src = '//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit';
    script.async = true;
    document.body.appendChild(script);
  }

  window.googleTranslateElementInit = function () {
    if (document.getElementById('google_translate_element')) {
      new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element');
    }
  };

  function triggerGoogleTranslate(langCode) {
    var selectField = document.querySelector('select.goog-te-combo');
    if (selectField) {
      selectField.value = langCode;
      selectField.dispatchEvent(new Event('change'));
    }
  }

  function initLanguageSwitcher() {
    var wrapper = document.querySelector('.tsp-language-switcher');
    if (!wrapper) return;

    var select = wrapper.querySelector('.tsp-language-switcher-select');
    if (!select) return;

    select.addEventListener('change', function () {
      var value = this.value;
      if (!value) return;
      if (value.startsWith('http')) {
        window.location.href = value;
      } else {
        triggerGoogleTranslate(value);
      }
    });

    loadGoogleTranslateScript();
  }

  document.addEventListener('DOMContentLoaded', initLanguageSwitcher);
})();