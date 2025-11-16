@extends('layouts.app')

@section('title','Minhas inscrições')
@section('h1','Minhas inscrições')

@section('content')
    <style>
        .d-none { display: none !important; }
    </style>

    <div id="alert" class="alert d-none"></div>

    <div id="not-logged" class="alert alert-warning d-none">
        Você precisa estar autenticado para ver suas inscrições.
        <a href="{{ route('login') }}" class="alert-link">Clique aqui para fazer login</a>.
    </div>

    <div id="list-container" class="card d-none">
        <div class="card-body">
            <p id="empty-msg" class="muted" style="display:none;">
                Você ainda não possui inscrições em eventos.
            </p>

            <div id="table-wrapper" style="display:none; overflow-x:auto;">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                    <tr>
                        <th>Evento</th>
                        <th>Data / Horário</th>
                        <th>Local</th>
                        <th>Status</th>
                        <th style="width: 120px;">Ações</th>
                    </tr>
                    </thead>
                    <tbody id="rows"></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        (function(){
            const alertBox  = document.getElementById('alert');
            const notLogged = document.getElementById('not-logged');
            const listCard  = document.getElementById('list-container');
            const emptyMsg  = document.getElementById('empty-msg');
            const tableWrap = document.getElementById('table-wrapper');
            const rowsBody  = document.getElementById('rows');

            function showAlert(msg, type='success'){
                alertBox.className = 'alert alert-' + type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hideAlert(){
                alertBox.classList.add('d-none');
            }
            function fmtDateTime(str){
                if (!str) return '-';
                const d = new Date(str);
                if (Number.isNaN(d.getTime())) return str;
                return d.toLocaleString('pt-BR');
            }
            function badgeStatus(status){
                const s = String(status || '').toLowerCase();
                if (s === 'confirmed') return '<span class="badge bg-success">Confirmada</span>';
                if (s === 'canceled')  return '<span class="badge bg-secondary">Cancelada</span>';
                return '<span class="badge bg-light text-dark">'+status+'</span>';
            }

            async function getJSON(url, opts = {}){
                const res  = await fetch(url, opts);
                const data = await res.json().catch(()=> ({}));
                return { res, data };
            }

            async function loadMyRegistrations(){
                hideAlert();

                notLogged.classList.add('d-none');

                const token = localStorage.getItem('token');
                console.log('[my-events] token:', token);

                if (!token) {
                    notLogged.classList.remove('d-none');
                    return;
                }

                try {
                    const { res, data } = await getJSON('/api/v1/my-registrations', {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json'
                        }
                    });

                    console.log('[my-events] status:', res.status, data);

                    if (res.status === 401) {
                        notLogged.classList.remove('d-none');
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                        return;
                    }

                    if (!res.ok) {
                        showAlert(data.message || 'Erro ao carregar suas inscrições.', 'danger');
                        return;
                    }

                    notLogged.classList.add('d-none');

                    const items = data.data || [];

                    listCard.classList.remove('d-none');

                    if (!items.length) {
                        emptyMsg.style.display  = 'block';
                        tableWrap.style.display = 'none';
                        return;
                    }

                    emptyMsg.style.display  = 'none';
                    tableWrap.style.display = 'block';
                    rowsBody.innerHTML      = '';

                    items.forEach(reg => {
                        const e = reg.event || {};
                        const tr = document.createElement('tr');

                        tr.innerHTML = `
                            <td>
                                <strong>${e.title ?? '-'}</strong><br>
                                <small class="text-muted">#${e.id ?? '-'}</small>
                            </td>
                            <td>
                                <div>${fmtDateTime(e.start_at)}</div>
                                <small class="text-muted">
                                    ${e.end_at ? 'até ' + fmtDateTime(e.end_at) : ''}
                                </small>
                            </td>
                            <td>${e.location ?? '-'}</td>
                            <td>${badgeStatus(reg.status)}</td>
                            <td>
                                ${e.id ? `
                                <a href="/events/${e.id}" class="btn btn-sm btn-outline-primary mb-1">
                                    Ver
                                </a>` : ''}
                            </td>
                        `;

                        rowsBody.appendChild(tr);
                    });

                } catch (err) {
                    console.error(err);
                    showAlert('Erro de rede ao carregar suas inscrições.', 'danger');
                }
            }

            loadMyRegistrations();
        })();
    </script>
@endsection
