(function(){
  function trapFocus(modal){
    var nodes = modal.querySelectorAll('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])');
    var f = Array.prototype.slice.call(nodes);
    if (!f.length) return function(){};
    var first = f[0], last = f[f.length-1];
    function onKey(e){
      if(e.key === 'Escape'){ modal.dispatchEvent(new CustomEvent('modal:close')); }
      if(e.key === 'Tab'){
        if(e.shiftKey && document.activeElement === first){ e.preventDefault(); last.focus(); }
        else if(!e.shiftKey && document.activeElement === last){ e.preventDefault(); first.focus(); }
      }
    }
    modal.addEventListener('keydown', onKey);
    return function(){ modal.removeEventListener('keydown', onKey); };
  }
  window.ModalA11y = { trapFocus: trapFocus };
})();