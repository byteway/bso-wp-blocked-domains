(function(){
    // bsoAdmin object localized from PHP
    if (!window.bsoAdmin) window.bsoAdmin = {};

    function alertMsg(msg){
        if (window.Swal) Swal.fire({text: msg}); else alert(msg);
    }
    function confirmMsg(msg){
        if (window.Swal) return Swal.fire({text: msg, icon: 'warning', showCancelButton: true, confirmButtonText: 'Yes', cancelButtonText: 'Cancel'});
        return new Promise(function(res){ res(confirm(msg)); });
    }
    function promptMsg(msg, defaultVal){
        if (window.Swal) return Swal.fire({title: msg, input: 'text', inputValue: defaultVal, showCancelButton: true, confirmButtonText: 'Save', cancelButtonText: 'Cancel'});
        return new Promise(function(res){ var v = prompt(msg, defaultVal); res({isConfirmed: v !== null, value: v}); });
    }

    // Utility to post and parse json
    function postAction(action, data){
        data = data || {};
        data.action = action;
        if (!data.nonce) data.nonce = bsoAdmin.nonce_manage;
        var fd = new FormData();
        Object.keys(data).forEach(function(k){ fd.append(k, data[k]); });
        return fetch(bsoAdmin.ajax_url, {method:'POST', body:fd, credentials:'same-origin'})
            .then(function(r){
                var ct = r.headers.get('content-type') || '';
                if (r.ok && ct.indexOf('application/json') !== -1) return r.json();
                return r.text().then(function(t){ throw new Error(t || 'server_error'); });
            });
    }

    function init(){
        // Add domain
        var addBtn = document.getElementById('bso-add-domain');
        if (addBtn) {
            addBtn.addEventListener('click', function(e){
                e.preventDefault();
                var fld = document.getElementById('bso-new-domain');
                var val = fld.value ? fld.value.trim() : '';
                if (!val) { alertMsg(bsoAdmin.strings.add_empty); return; }
                if (val.indexOf('.') === -1 || /\s|@/.test(val)) { alertMsg(bsoAdmin.strings.add_invalid); return; }
                postAction('bso_add_domain', {domain: val}).then(function(res){
                    if (!res.success) { alertMsg(bsoAdmin.strings.add_failed + ': ' + (res.data && res.data.message ? res.data.message : 'error')); return; }
                    // toast
                    if (window.Swal) Swal.fire({toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, title: 'Domain added: ' + res.data.domain});
                    else alert('Domain added: ' + res.data.domain);
                    setTimeout(function(){ location.reload(); }, 600);
                }).catch(function(){ alertMsg(bsoAdmin.strings.request_failed); });
            });
        }

        // Delegated edit/delete handlers
        document.addEventListener('click', function(e){
            var t = e.target;
            if (t.matches('.bso-edit-row')) {
                e.preventDefault();
                var old = t.getAttribute('data-domain') || '';
                if (!old) {
                    var tr = t.closest('tr');
                    if (tr) {
                        var cell = tr.querySelector('td.column-domain');
                        if (cell) old = cell.textContent.trim();
                    }
                }
                promptMsg('Edit domain', old).then(function(res){
                    if (!res || !res.isConfirmed) return;
                    var newVal = res.value ? res.value.trim() : '';
                    if (!newVal) { alertMsg('Empty domain not allowed'); return; }
                    postAction('bso_update_domain', {old: old, new: newVal}).then(function(r){
                        if (!r.success) { alertMsg('Update failed: ' + (r.data && r.data.message ? r.data.message : 'error')); return; }
                        Swal.fire({toast:true, position:'top-end', showConfirmButton:false, timer:2000, title:'Domain updated'});
                        setTimeout(function(){ location.reload(); }, 600);
                    }).catch(function(){ alertMsg(bsoAdmin.strings.request_failed); });
                });
            }
            if (t.matches('.bso-delete-row')) {
                e.preventDefault();
                var domain = t.getAttribute('data-domain');
                confirmMsg('Delete domain "' + domain + '"?').then(function(res){
                    var ok = res && (res.isConfirmed || res === true);
                    if (!ok) return;
                    var fd = new FormData(); fd.append('action','bso_delete_domains'); fd.append('nonce', bsoAdmin.nonce_manage); fd.append('domains[]', domain);
                    fetch(bsoAdmin.ajax_url, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){
                        // try to parse json safely
                        return r.text().then(function(txt){ try { return JSON.parse(txt); } catch(e) { return {success:false, data:{message:txt}}; } });
                    }).then(function(rr){
                        if (!rr.success) { alertMsg(bsoAdmin.strings.delete_failed + ': ' + (rr.data && rr.data.message ? rr.data.message : 'error')); return; }
                        var undoKey = rr.data && rr.data.undo_key ? rr.data.undo_key : null;
                        var deletedCount = rr.data && rr.data.deleted_count ? rr.data.deleted_count : 0;
                        var doRestore = confirm('Deleted ' + deletedCount + ' domain(s). Click OK to restore, Cancel to keep deleted.');
                        if (doRestore && undoKey) {
                            var fd2 = new FormData(); fd2.append('action','bso_restore_domains'); fd2.append('nonce', bsoAdmin.nonce_manage); fd2.append('key', undoKey);
                            fetch(bsoAdmin.ajax_url, {method:'POST', body:fd2, credentials:'same-origin'}).then(function(r2){ return r2.json(); }).then(function(res2){ if (res2.success) { alertMsg('Restored ' + (res2.data.restored_count || 0)); setTimeout(function(){ location.reload(); },600);} else { alertMsg('Restore failed'); } }).catch(function(){ alertMsg(bsoAdmin.strings.request_failed); });
                        } else {
                            setTimeout(function(){ location.reload(); }, 300);
                        }
                    }).catch(function(){ alertMsg(bsoAdmin.strings.request_failed); });
                });
            }
        });

        // Bulk delete
        var delBtn = document.getElementById('bso-delete-selected');
        if (delBtn) delBtn.addEventListener('click', function(e){
            e.preventDefault();
            var selected = [];
            document.querySelectorAll('.bso-domain-chk').forEach(function(c){ if (c.checked) selected.push(c.value); });
            var selectAllMatching = document.getElementById('bso-select-all-matching');
            var selectAllMatchingFlag = selectAllMatching ? selectAllMatching.checked : false;
            if (!selected.length && !selectAllMatchingFlag) { alertMsg('Select at least one domain to delete.'); return; }
            confirmMsg(bsoAdmin.strings.delete_confirm).then(function(res){
                var ok = res && (res.isConfirmed || res === true);
                if (!ok) return;
                var fd = new FormData(); fd.append('action','bso_delete_domains'); fd.append('nonce', bsoAdmin.nonce_manage);
                if (selectAllMatchingFlag) { fd.append('delete_all','1'); fd.append('search', document.querySelector('input[name="bso_search"]').value || ''); }
                else { selected.forEach(function(v){ fd.append('domains[]', v); }); }
                fetch(bsoAdmin.ajax_url, {method:'POST', body:fd, credentials:'same-origin'}).then(function(r){ return r.json(); }).then(function(rr){
                    if (!rr.success) { alertMsg('Delete failed'); return; }
                    Swal.fire({toast:true, position:'top-end', showConfirmButton:false, timer:2000, title: bsoAdmin.strings.deleted.replace('%d', rr.data.deleted_count || 0)});
                    setTimeout(function(){ location.reload(); }, 600);
                }).catch(function(){ alertMsg(bsoAdmin.strings.request_failed); });
            });
        });
        // Import: initialize chunked import and process chunks
        var importBtn = document.getElementById('bso-confirm-import');
        if (importBtn) {
            importBtn.addEventListener('click', function(e){
                e.preventDefault();
                var previewField = document.getElementById('import_preview');
                if (!previewField) return alertMsg('Nothing to import');
                importBtn.disabled = true;
                // init
                postAction('bso_import_init', {import_preview: previewField.value, nonce: bsoAdmin.nonce_save}).then(function(initRes){
                    if (!initRes.success) { alertMsg('Import init failed: ' + (initRes.data && initRes.data.message ? initRes.data.message : 'error')); importBtn.disabled=false; return; }
                    var key = initRes.data.key;
                    var total = parseInt(initRes.data.total,10)||0;
                    var chunkSize = 200;
                    var importedSoFar = 0;
                    (async function(){
                        for (var start=0; start<total; start+=chunkSize) {
                            var fd = new FormData(); fd.append('action','bso_import_chunk'); fd.append('nonce', bsoAdmin.nonce_save); fd.append('key', key); fd.append('start', start); fd.append('length', chunkSize);
                            try {
                                var resp = await fetch(bsoAdmin.ajax_url, {method:'POST', body:fd, credentials:'same-origin'});
                                var json = await resp.json();
                                if (!json.success) { throw new Error(json.data && json.data.message ? json.data.message : 'chunk_failed'); }
                                importedSoFar += parseInt(json.data.imported,10)||0;
                                // update UI if present
                                var linesImportedText = document.getElementById('bso-lines-imported'); if (linesImportedText) linesImportedText.textContent = importedSoFar;
                            } catch(err) {
                                alertMsg('Import failed: ' + err.message);
                                importBtn.disabled = false; return;
                            }
                        }
                        alertMsg('Imported ' + importedSoFar + ' domains.');
                        importBtn.disabled = false; window.location.reload();
                    })();
                }).catch(function(){ alertMsg('Import init failed'); importBtn.disabled=false; });
            });
        }

        // Export handled via normal navigation; no change here
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
