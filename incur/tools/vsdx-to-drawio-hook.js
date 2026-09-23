(() => {
  if (window.__hdsHookInstalled) return;
  window.__hdsHookInstalled = true;
  window.__hdsUi = null;
  const tryHook = () => {
    if (!window.EditorUi || window.EditorUi.__hdsPatched) return !!window.EditorUi;
    window.EditorUi.__hdsPatched = true;
    const proto = window.EditorUi.prototype;
    if (typeof proto.init === 'function') {
      const oldInit = proto.init;
      proto.init = function () {
        window.__hdsUi = this;
        return oldInit.apply(this, arguments);
      };
    }
    if (typeof proto.fileLoaded === 'function') {
      const oldFL = proto.fileLoaded;
      proto.fileLoaded = function () {
        window.__hdsUi = this;
        window.__hdsLastFileLoaded = Date.now();
        return oldFL.apply(this, arguments);
      };
    }
    return true;
  };
  tryHook();
  window.__hdsHookInterval = setInterval(() => {
    if (tryHook()) clearInterval(window.__hdsHookInterval);
  }, 5);
})();