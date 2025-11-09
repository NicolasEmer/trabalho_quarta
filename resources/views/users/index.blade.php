@extends('layouts.app')

@section('title','Usuários')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>Usuários</h3>
        <a class="btn btn-success btn-sm" href="/users/create">Novo</a>
    </div>

    <div id="alert" class="alert d-none"></div>

    <table class="table table-bordered table-sm">
        <thead>
        <tr>
            <th>#</th>
            <th>CPF</th>
            <th>Nome</th>
            <th>Email</th>
            <th>Completo?</th>
            <th>Ações</th>
        </tr>
        </thead>
        <tbody id="tbody-users">
        <tr><td colspan="6" class="text-center">Carregando...</td></tr>
        </tbody>
    </table>

    <div class="mt-3 d-flex justify-content-between" id="pagination" style="display:none;">
        <button id="btn-prev" class="btn btn-secondary btn-sm">Anterior</button>
        <button id="btn-next" class="btn btn-secondary btn-sm">Próximo</button>
    </div>

    <script>
        (function () {

            const alertBox = document.getElementById('alert');
            function showAlert(msg, type='warning') {
                alertBox.className = 'alert alert-' + type;
                alertBox.textContent = msg;
                alertBox.classList.remove('d-none');
            }
            function hideAlert() { alertBox.classList.add('d-none'); }

            const tbody = document.getElementById('tbody-users');
            const btnPrev = document.getElementById('btn-prev');
            const btnNext = document.getElementById('btn-next');
            const pagDiv = document.getElementById('pagination');

            const token = localStorage.getItem('token');
            if (!token) {
                showAlert('Você precisa estar autenticado. Faça login.', 'danger');
                setTimeout(() => location.href = '/login', 1200);
                return;
            }

            let nextUrl = null;
            let prevUrl = null;

            async function loadUsers(url = '/api/v1/users') {
                hideAlert();

                tbody.innerHTML = `
            <tr><td colspan="6" class="text-center">Carregando...</td></tr>
        `;

                try {
                    const res = await fetch(url, {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json'
                        }
                    });

                    if (res.status === 401) {
                        showAlert('Sessão expirada. Faça login novamente.', 'danger');
                        setTimeout(() => location.href = '/login', 1000);
                        return;
                    }

                    if (!res.ok) throw new Error('Erro ao carregar lista de usuários');

                    const json = await res.json();

                    const list = json.data ?? json; // API Resource padrão
                    tbody.innerHTML = '';

                    if (!Array.isArray(list) || list.length === 0) {
                        tbody.innerHTML = `<tr><td colspan="6" class="text-center">Nenhum usuário encontrado.</td></tr>`;
                        return;
                    }

                    list.forEach(u => {
                        tbody.innerHTML += `
                    <tr>
                        <td>${u.id}</td>
                        <td>${u.cpf || ''}</td>
                        <td>${u.name || ''}</td>
                        <td>${u.email || ''}</td>
                        <td>
                            ${u.completed
                            ? '<span class="badge text-bg-success">Sim</span>'
                            : '<span class="badge text-bg-warning">Não</span>'}
                        </td>
                        <td class="d-flex gap-2">
                            <a class="btn btn-primary btn-sm" href="/users/${u.id}/edit">Editar</a>
                            <button class="btn btn-danger btn-sm" onclick="deleteUser(${u.id})">Remover</button>
                        </td>
                    </tr>
                `;
                    });

                    // Paginação (se sua API expõe links)
                    nextUrl = json.links?.next ?? null;
                    prevUrl = json.links?.prev ?? null;

                    if (nextUrl || prevUrl) {
                        pagDiv.style.display = 'flex';
                        btnPrev.disabled = !prevUrl;
                        btnNext.disabled = !nextUrl;
                    } else {
                        pagDiv.style.display = 'none';
                    }

                } catch (e) {
                    showAlert(e.message || 'Erro ao carregar usuários.', 'danger');
                }
            }

            // excluir usuário
            window.deleteUser = async function (id) {
                if (!confirm('Excluir este usuário?')) return;

                try {
                    const res = await fetch(`/api/v1/users/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json'
                        }
                    });

                    if (!res.ok) {
                        const j = await res.json().catch(()=>({}));
                        showAlert(j.message || 'Erro ao excluir.', 'danger');
                        return;
                    }

                    showAlert('Usuário removido com sucesso!', 'success');
                    loadUsers();

                } catch (e) {
                    showAlert('Falha na exclusão.', 'danger');
                }
            };

            btnNext.addEventListener('click', () => nextUrl && loadUsers(nextUrl));
            btnPrev.addEventListener('click', () => prevUrl && loadUsers(prevUrl));

            loadUsers();

        })();
    </script>

@endsection
