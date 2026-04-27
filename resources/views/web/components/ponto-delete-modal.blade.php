@can('delete-time-records')
<div id="delete-ponto-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50" role="dialog" aria-modal="true" aria-labelledby="delete-ponto-title">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200" onclick="event.stopPropagation()">
        <h3 id="delete-ponto-title" class="text-lg font-semibold text-slate-800 mb-1">Excluir registo de ponto</h3>
        <p class="text-sm text-slate-600 mb-4" id="delete-ponto-label"></p>
        <p class="text-xs text-rose-800 bg-rose-50 border border-rose-100 rounded-lg px-3 py-2 mb-4 leading-relaxed">
            Uso para teste/correção. Confirme com a <strong>palavra-passe da sua conta</strong> e digite <span class="font-mono font-bold">EXCLUIR</span> (maiúsculas). O ponto deixa de aparecer no histórico; o dia é recalculado no banco de horas.
        </p>
        <form id="delete-ponto-form" method="post" class="space-y-3">
            @csrf
            @method('DELETE')
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1" for="delete-ponto-current-password">Palavra-passe atual</label>
                <input type="password" name="current_password" id="delete-ponto-current-password" required autocomplete="current-password" class="w-full text-sm border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-rose-200 focus:border-rose-400 outline-none" />
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1" for="delete-ponto-confirm">Confirmação: digite <span class="font-mono font-bold">EXCLUIR</span></label>
                <input type="text" name="confirm_excluir" id="delete-ponto-confirm" required placeholder="EXCLUIR" autocomplete="off" class="w-full text-sm font-mono border border-slate-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-rose-200 focus:border-rose-400 outline-none" title="EXCLUIR em maiúsculas" />
            </div>
            <div class="flex items-center justify-end gap-2 pt-2">
                <button type="button" class="text-sm text-slate-600 px-3 py-2 hover:bg-slate-100 rounded-lg" onclick="closeDeletePontoModal()">Cancelar</button>
                <button type="submit" class="text-sm font-medium text-white bg-rose-600 px-4 py-2 rounded-lg hover:bg-rose-700">Excluir ponto</button>
            </div>
        </form>
    </div>
</div>
<script>
(function() {
  function openDeletePontoModal(btn) {
    var modal = document.getElementById('delete-ponto-modal');
    var form = document.getElementById('delete-ponto-form');
    var label = document.getElementById('delete-ponto-label');
    if (!modal || !form || !label) return;
    form.setAttribute('action', btn.getAttribute('data-destroy-url'));
    label.textContent = btn.getAttribute('data-label') || '';
    var p = form.querySelector('input[name=current_password]');
    var c = form.querySelector('input[name=confirm_excluir]');
    if (p) p.value = '';
    if (c) c.value = '';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    setTimeout(function() { if (p) p.focus(); }, 100);
  }
  function closeDeletePontoModal() {
    var modal = document.getElementById('delete-ponto-modal');
    if (modal) { modal.classList.add('hidden'); document.body.style.overflow = ''; }
  }
  window.openDeletePontoModal = openDeletePontoModal;
  window.closeDeletePontoModal = closeDeletePontoModal;
  var m = document.getElementById('delete-ponto-modal');
  if (m) m.addEventListener('click', function(e) { if (e.target === m) closeDeletePontoModal(); });
})();
</script>
@endcan
