@extends('layouts.app')
@section('title','Detalhes do evento')
@section('h1','Detalhes do evento')

@section('content')
    <div id="alert" class="alert d-none"></div>

    <div id="card" aria-live="polite">
        <p class="muted">Carregando…</p>
    </div>

    <div id="registration-block" class="card mt-3" aria-live="polite">
        <div class="card-body">
            <h5 class="card-title">Inscrição</h5>
            <p class="card-text" id="registration-info" style="margin-bottom: 0.75rem;">
                Informe seu CPF para se inscrever neste evento. Se já tiver conta, usaremos seu cadastro automaticamente.
            </p>

            <form id="register-form" class="row g-2" autocomplete="off">
                <div class="col-sm-4" id="cpf-group">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf" id="cpf" class="form-control" placeholder="000.000.000-00" required>
                </div>
                <div class="col-sm-4 align-self-end">
                    <button type="submit" class="btn btn-primary" id="btnRegister">
                        <span class="btn-text">Inscrever-se</span>
                        <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                    </button>
                </div>
            </form>

            <small class="text-muted d-block mt-2" id="cpf-hint">
                * A inscrição também funciona com seu login simplificado via CPF.
            </small>

            <hr class="mt-3 mb-3">

            <button type="button" class="btn btn-success" id="btnPresence">
                <span class="btn-presence-text">Registrar presença</span>
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
            </button>
            <small class="text-muted d-block mt-2">
                A presença só pode ser registrada por inscritos, durante ou após o horário do evento.
            </small>
        </div>
    </div>

    <div class="mt-3">
        <a id="btnEdit" class="btn">Editar</a>
        <button id="btnDel" class="btn btn-danger">Excluir</button>
        <a class="btn btn-outline" href="{{ route('events.index') }}">Voltar</a>
        <button id="btnUnregister" class="btn btn-outline-danger ms-2">
            Cancelar minha inscrição
        </button>

        <button id="btnConfirmPresence" class="btn btn-success ms-2">
            Confirmar presença / Emitir certificado
        </button>

        <button id="btnViewCertificate" class="btn btn-outline-success ms-2 d-none">
            Ver meu certificado
        </button>
    </div>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const id         = @json($id);
            const $alert     = document.getElementById('alert');
            const $card      = document.getElementById('card');
            const $btnEdit   = document.getElementById('btnEdit');
            const $btnDel    = document.getElementById('btnDel');
            const $regInfo   = document.getElementById('registration-info');
            const $regForm   = document.getElementById('register-form');
            const $cpfGroup  = document.getElementById('cpf-group');
            const $cpf       = document.getElementById('cpf');
            const $cpfHint   = document.getElementById('cpf-hint');
            const $btnReg    = document.getElementById('btnRegister');
            const $btnRegTxt = $btnReg.querySelector('.btn-text');
            const $btnRegSpn = $btnReg.querySelector('.spinner-border');
            const $btnUnregister      = document.getElementById('btnUnregister');
            const $btnConfirmPresence = document.getElementById('btnConfirmPresence');
            const $btnViewCertificate = document.getElementById('btnViewCertificate');

            const $btnPresence      = document.getElementById('btnPresence');
            const $btnPresenceTxt   = $btnPresence.querySelector('.btn-presence-text');
            const $btnPresenceSpn   = $btnPresence.querySelector('.spinner-border');

            let currentCertPdfUrl = null;

            // Detecção de login (sessão Laravel + token API)
            const sessionLogged = @json(auth()->check());
            const storedToken   = localStorage.getItem('token');
            const token         = storedToken;
            const isLogged      = !!sessionLogged || !!storedToken;

            console.log('sessionLogged=', sessionLogged, 'storedToken=', storedToken, 'isLogged=', isLogged);

            function showAlert(msg, type='success'){
                $alert.className = 'alert alert-' + type;
                $alert.textContent = msg;
                $alert.classList.remove('d-none');
                if (type === 'success') {
                    setTimeout(()=> $alert.classList.add('d-none'), 2500);
                }
            }
            function hideAlert(){ $alert.classList.add('d-none'); }

            function onlyDigits(s){ return String(s || '').replace(/\D+/g,''); }

            function fmtDateTime(str){
                if (!str) return '-';
                const d = new Date(str);
                if (Number.isNaN(d.getTime())) return str;
                return d.toLocaleString('pt-BR');
            }

            function toggleRegisterLoading(on){
                $btnReg.disabled = on;
                $btnRegSpn.classList.toggle('d-none', !on);
                $btnRegTxt.textContent = on ? 'Enviando...' : (isLogged ? 'Inscrever-me neste evento' : 'Inscrever-se');
            }

            function togglePresenceLoading(on){
                $btnPresence.disabled = on;
                $btnPresenceSpn.classList.toggle('d-none', !on);
                $btnPresenceTxt.textContent = on ? 'Registrando...' : 'Registrar presença';
            }

            async function getJSON(url, opts = {}){
                const res = await fetch(url, opts);
                const data = await res.json().catch(() => ({}));
                return { res, data };
            }

            async function apiFetch(url, opts = {}) {
                const res  = await fetch(url, opts);
                const data = await res.json().catch(()=> ({}));
                return { res, data };
            }

            async function loadEvent(){
                try {
                    const { res, data } = await getJSON(`/api/v1/events/${id}`, {
                        headers: { 'Accept':'application/json' }
                    });

                    if (!res.ok) {
                        $card.innerHTML = `<p class="muted">Não foi possível carregar o evento.</p>`;
                        return;
                    }

                    const e = data.data || data;
                    let vagasInfo = '';
                    if (typeof e.capacity !== 'undefined' && e.capacity !== null) {
                        vagasInfo = `<p class="mt-2 mb-1"><strong>Capacidade:</strong> ${e.capacity} vagas</p>`;
                    }

                    $card.innerHTML = `
                        <h2 class="h4">${e.title ?? '-'}</h2>
                        <p class="mt-2 mb-1"><strong>Início:</strong> ${fmtDateTime(e.start_at)}</p>
                        <p class="mt-1 mb-1"><strong>Término:</strong> ${fmtDateTime(e.end_at)}</p>
                        ${vagasInfo}
                        <p class="mt-2 mb-1"><strong>Local:</strong> ${e.location ?? '-'}</p>
                        <p class="mt-2" style="white-space:pre-wrap">${e.description ?? ''}</p>
                    `;

                    $btnEdit.href = `{{ url('/events') }}/${id}/edit`;
                } catch (_) {
                    $card.innerHTML = `<p class="muted">Erro de rede ao carregar.</p>`;
                }
            }

            // Ajuste de UI quando está logado: remove CPF e texto
            if (isLogged) {
                if ($cpfGroup && $cpfGroup.parentNode) {
                    $cpfGroup.parentNode.removeChild($cpfGroup);
                }

                if ($cpfHint && $cpfHint.parentNode) {
                    $cpfHint.parentNode.removeChild($cpfHint);
                }

                if ($cpf) {
                    $cpf.removeAttribute('required');
                    $cpf.value = '';
                }

                $regInfo.textContent = 'Você está autenticado. Clique em "Inscrever-me neste evento" para se inscrever usando seu cadastro.';
                $btnRegTxt.textContent = 'Inscrever-me neste evento';
            }

            function travarCertificadoUI(msg, pdfUrl) {
                if ($btnUnregister) {
                    $btnUnregister.classList.add('d-none');
                }
                if ($btnConfirmPresence) {
                    $btnConfirmPresence.disabled = true;
                    $btnConfirmPresence.textContent = msg || 'Certificado já emitido';
                }
                if (pdfUrl) {
                    currentCertPdfUrl = pdfUrl;
                    $btnViewCertificate.classList.remove('d-none');
                }

                $regForm.classList.add('d-none');
                $regInfo.textContent = 'Você já possui certificado para este evento.';
            }

            async function loadStatus() {
                if (!isLogged) return;

                // 1) Checa se já existe certificado
                try {
                    const { res, data } = await apiFetch(`/api/v1/events/${id}/certificate`, {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json',
                        }
                    });

                    if (res.ok && data.data) {
                        const cert = data.data;
                        const pdfUrl = cert.pdf_url ?? null;

                        travarCertificadoUI('Certificado já emitido', pdfUrl);
                        return;
                    }
                } catch (_) {}

                // 2) Se não tem certificado, verifica se já está inscrito
                try {
                    const { res } = await apiFetch(`/api/v1/events/${id}/register`, {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json',
                        }
                    });

                    if (res.ok) {
                        $regInfo.textContent = 'Você está inscrito neste evento.';
                        $regForm.classList.add('d-none');
                    }
                } catch (_) {}
            }

            $btnDel.addEventListener('click', async ()=>{
                if (!confirm('Excluir este evento?')) return;

                hideAlert();
                const tokenLocal = localStorage.getItem('token');
                if (!tokenLocal) {
                    return showAlert('Você precisa estar autenticado para excluir eventos.', 'danger');
                }

                try {
                    const { res, data } = await getJSON(`/api/v1/events/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + tokenLocal,
                            'Accept': 'application/json'
                        }
                    });

                    if (res.ok) {
                        showAlert('Evento excluído com sucesso!', 'success');
                        setTimeout(()=> location.href = '{{ route('events.index') }}', 1000);
                    } else if (res.status === 401){
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                    } else {
                        showAlert(data.message || 'Erro ao excluir.', 'danger');
                    }
                } catch (_) {
                    showAlert('Erro de rede ao excluir.', 'danger');
                }
            });

            // Inscrição: usa CPF se não estiver logado, usuário autenticado se tiver token
            $regForm.addEventListener('submit', async (ev)=>{
                ev.preventDefault();
                hideAlert();

                const tokenLocal = localStorage.getItem('token');
                const loggedNow  = !!tokenLocal;

                let bodyPayload = {};

                // Se NÃO estiver logado, exige CPF
                if (!loggedNow) {
                    const cpfValue  = $cpf ? $cpf.value : '';
                    const cpfDigits = onlyDigits(cpfValue);

                    if (cpfDigits.length !== 11) {
                        return showAlert('CPF inválido: informe 11 dígitos.', 'danger');
                    }

                    bodyPayload = { cpf: cpfDigits };
                }

                toggleRegisterLoading(true);

                try {
                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    };
                    if (loggedNow && tokenLocal) {
                        headers['Authorization'] = 'Bearer ' + tokenLocal;
                    }

                    const { res, data } = await getJSON(`/api/v1/events/${id}/register`, {
                        method: 'POST',
                        headers,
                        body: JSON.stringify(bodyPayload)
                    });

                    if (res.ok) {
                        showAlert(data.message || 'Inscrição realizada com sucesso!', 'success');
                        $regInfo.textContent = 'Você está inscrito neste evento.';
                        $regForm.classList.add('d-none');
                    } else if (res.status === 409) {
                        showAlert(data.message || 'Evento lotado.', 'warning');
                    } else if (res.status === 422) {
                        const msg = (data.errors && data.errors.cpf && data.errors.cpf[0])
                            || data.message
                            || 'Dados inválidos.';
                        showAlert(msg, 'danger');
                    } else if (res.status === 401) {
                        showAlert('Sessão expirada. Tente novamente fazer login.', 'danger');
                    } else {
                        showAlert(data.message || 'Não foi possível realizar a inscrição.', 'danger');
                    }
                } catch (_) {
                    showAlert('Erro de rede ao tentar inscrever.', 'danger');
                } finally {
                    toggleRegisterLoading(false);
                }
            });

            $btnUnregister.addEventListener('click', async () => {
                if (!confirm('Tem certeza que deseja cancelar sua inscrição neste evento?')) {
                    return;
                }

                hideAlert();
                const tokenLocal = localStorage.getItem('token');
                if (!tokenLocal) {
                    return showAlert('Você precisa estar autenticado para cancelar sua inscrição.', 'danger');
                }

                try {
                    const { res, data } = await apiFetch(`/api/v1/events/${id}/register`, {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + tokenLocal,
                            'Accept': 'application/json'
                        }
                    });

                    if (res.ok) {
                        showAlert(data.message || 'Inscrição cancelada com sucesso.', 'success');
                        $regInfo.textContent = 'Informe seu CPF para se inscrever neste evento. Se já tiver conta, usaremos seu cadastro automaticamente.';
                        $regForm.classList.remove('d-none');
                        if (!isLogged && $cpfGroup) {
                            $cpfGroup.classList.remove('d-none');
                        }
                        if (!isLogged && $cpfHint) {
                            $cpfHint.classList.remove('d-none');
                        }
                    } else if (res.status === 404) {
                        showAlert(data.message || 'Você não possui inscrição ativa neste evento.', 'warning');
                    } else if (res.status === 401) {
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                    } else {
                        showAlert(data.message || 'Erro ao cancelar inscrição.', 'danger');
                    }
                } catch (err) {
                    showAlert('Erro de rede ao cancelar inscrição.', 'danger');
                }
            });

            $btnPresence.addEventListener('click', async () => {
                hideAlert();

                const tokenLocal = localStorage.getItem('token');
                if (!tokenLocal) {
                    return showAlert('Você precisa estar autenticado para registrar sua presença.', 'danger');
                }

                togglePresenceLoading(true);

                try {
                    const { res, data } = await apiFetch(`/api/v1/events/${id}/presence`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + tokenLocal,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({})
                    });

                    if (res.ok) {
                        showAlert(data.message || 'Presença registrada com sucesso!', 'success');
                        if (data.data && data.data.presence_at) {
                            $regInfo.textContent = 'Sua presença foi registrada em: ' + fmtDateTime(data.data.presence_at);
                        }
                    } else if (res.status === 404) {
                        showAlert(data.message || 'Você não possui inscrição ativa neste evento.', 'warning');
                    } else if (res.status === 422) {
                        showAlert(data.message || 'Não foi possível registrar presença (verifique horário do evento ou se já foi registrada).', 'warning');
                    } else if (res.status === 401) {
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                    } else {
                        showAlert(data.message || 'Erro ao registrar presença.', 'danger');
                    }
                } catch (err) {
                    showAlert('Erro de rede ao registrar presença.', 'danger');
                } finally {
                    togglePresenceLoading(false);
                }
            });

            $btnViewCertificate.addEventListener('click', () => {
                if (currentCertPdfUrl) {
                    window.open(currentCertPdfUrl, '_blank');
                }
            });

            $btnConfirmPresence.addEventListener('click', async () => {
                hideAlert();

                if (!confirm('Confirmar sua presença neste evento e emitir certificado?')) {
                    return;
                }

                if (!token) {
                    showAlert('Você precisa estar autenticado para confirmar presença.', 'danger');
                    setTimeout(()=> location.href = '/login', 900);
                    return;
                }

                try {
                    const { res, data } = await apiFetch('/api/v1/certificates', {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ event_id: id })
                    });

                    if (res.status === 409) {
                        const pdfUrl = data.data && data.data.pdf_url ? data.data.pdf_url : null;
                        showAlert(data.message || 'Você já possui certificado para este evento.', 'info');
                        travarCertificadoUI('Certificado já emitido', pdfUrl);
                        if (pdfUrl) {
                            window.open(pdfUrl, '_blank');
                        }
                        return;
                    }

                    if (!res.ok) {
                        console.error('Erro cert API:', res.status, data);
                        showAlert(
                            data.message || ('Erro ao confirmar presença / emitir certificado. (HTTP ' + res.status + ')'),
                            'danger'
                        );
                        return;
                    }

                    const pdfUrl = data.data && data.data.pdf_url ? data.data.pdf_url : null;

                    showAlert('Presença confirmada e certificado emitido com sucesso!', 'success');
                    travarCertificadoUI('Certificado emitido', pdfUrl);

                    if (pdfUrl) {
                        window.open(pdfUrl, '_blank');
                    }
                } catch (e) {
                    showAlert('Erro de rede ao confirmar presença.', 'danger');
                }
            });

            loadEvent();
            loadStatus();
        });
    </script>
@endsection
