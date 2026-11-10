<script>
    // small helpers shared by the agency pages
    window.agToast = function(message, ok) {
        var box = document.createElement('div');
        box.className = 'alert ag-toast ' + (ok ? 'alert-success' : 'alert-danger');
        box.textContent = message;
        document.body.appendChild(box);
        setTimeout(function() { box.remove(); }, ok ? 2200 : 5000);
    };
    window.agPost = function(url, data, onDone) {
        var body = (data instanceof FormData) ? new URLSearchParams(data) : new URLSearchParams(data || {});
        fetch(url, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(res) { return res.json().catch(function() { return {}; }).then(function(json) { return { ok: res.ok, json: json }; }); })
            .then(function(result) {
                if (result.ok) {
                    agToast('انجام شد', true);
                    if (onDone) { onDone(result.json); }
                    else if (result.json.next_url) { setTimeout(function() { location.href = result.json.next_url; }, 600); }
                    else { setTimeout(function() { location.reload(); }, 600); }
                } else {
                    var messages = result.json.messages || ['خطا در انجام عملیات'];
                    if (!Array.isArray(messages)) { messages = Object.values(messages); }
                    agToast([].concat.apply([], messages).join(' — '), false);
                }
            })
            .catch(function() { agToast('ارتباط با سرور برقرار نشد', false); });
    };
    document.addEventListener('submit', function(event) {
        var form = event.target;
        if (form.classList && form.classList.contains('ag-form')) {
            event.preventDefault();
            if (form.dataset.confirm && !confirm(form.dataset.confirm)) { return; }
            agPost(form.action, new FormData(form));
        }
    });
</script>
