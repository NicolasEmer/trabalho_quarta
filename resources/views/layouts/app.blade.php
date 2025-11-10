<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>@yield('title','Sistema de Eventos')</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,"Helvetica Neue",Arial,sans-serif;margin:24px;color:#111}
        .container{max-width:960px;margin:0 auto}
        .row{display:flex;gap:12px}
        .grow{flex:1}
        .muted{color:#666;font-size:.9em}
        .btn{display:inline-block;padding:8px 12px;border:1px solid #ccc;border-radius:8px;background:#f9f9f9;cursor:pointer;text-decoration:none;color:#111}
        .btn:hover{background:#f0f0f0}
        .btn-primary{background:#2563eb;color:#fff;border:1px solid #1d4ed8}
        .btn-primary:hover{background:#1d4ed8}
        .btn-danger{background:#dc2626;color:#fff;border-color:#b91c1c}
        .btn-outline{background:transparent}
        .table{width:100%;border-collapse:collapse;margin-top:12px}
        .table th,.table td{border-bottom:1px solid #eee;padding:8px;text-align:left}
        .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:.8em}
        .badge.green{background:#e7f9ef;color:#166534}
        .badge.gray{background:#f3f4f6;color:#374151}
        .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:12px 0}
        input[type="text"],input[type="datetime-local"],textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:8px}
        nav.pagination{display:flex;gap:6px;align-items:center;margin-top:12px;flex-wrap:wrap}
        nav.pagination a{padding:6px 10px;border:1px solid #ddd;border-radius:6px;text-decoration:none;color:#111}
        nav.pagination .current{background:#2563eb;color:#fff;border-color:#1d4ed8}
        .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}


        #userBox{
            position:fixed; top:10px; left:10px; z-index:9999;
            background:#f3f4f6; padding:6px 10px; border-radius:6px;
            font-size:14px; color:#111; border:1px solid #ddd;
            display:flex; align-items:center; gap:8px; flex-wrap:wrap;
        }
        #userBox .btn-mini{ padding:4px 8px; border-radius:6px; font-size:12px; }
        #warnIncomplete{
            display:none; margin-bottom:12px;
            background:#fff7d6; border:1px solid #f7e0a3; color:#7a5b00;
            padding:10px 12px; border-radius:8px;
        }
    </style>
    <script>
        const API_BASE = '/api/v1';

        async function api(path, {method='GET', body, headers} = {}) {
            const opts = { method, headers: {'Content-Type':'application/json', ...(headers||{})} };
            if (body !== undefined) opts.body = JSON.stringify(body);
            const res = await fetch(API_BASE + path, opts);
            const json = await res.json().catch(()=> ({}));
            if (!res.ok) {
                const msg = json?.message || 'Erro na requisição';
                throw new Error(msg);
            }
            return json;
        }

        function fmtDateTimeLocal(str){
            if(!str) return '';
            const d = (str.includes('Z')||str.includes('+')) ? new Date(str) : new Date(str.replace(' ','T'));
            const pad = n => String(n).padStart(2,'0');
            return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
        }

        function formToPayload(form){
            const fd = new FormData(form);
            const data = Object.fromEntries(fd.entries());
            data.is_all_day = fd.has('is_all_day');
            data.is_public  = fd.has('is_public');

            for (const k of ['description','location','end_at']) {
                if (data[k] === '') data[k] = null;
            }
            return data;
        }

        function qs(name, def=null){
            const u = new URL(location.href);
            return u.searchParams.get(name) ?? def;
        }

        function setQS(params){
            const u = new URL(location.href);
            Object.entries(params).forEach(([k,v])=>{
                if(v===null||v===undefined||v==='') u.searchParams.delete(k);
                else u.searchParams.set(k,v);
            });
            history.replaceState(null,'',u);
        }

        function toast(msg, ok=true){
            alert(msg);
        }
    </script>
    @yield('head')
</head>
<body>


<div id="userBox">Carregando usuário…</div>

<div class="container">

    <div id="warnIncomplete">
        <strong>Atenção:</strong> seu cadastro ainda não está completo.
        <a href="{{ route('users.complete') }}" class="btn" style="margin-left:8px">Completar cadastro</a>
    </div>

    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h1 style="margin:0;font-size:1.5rem">@yield('h1','Sistema de Eventos')</h1>
    </header>

    @yield('content')
</div>

<script>
    (function(){
        const box  = document.getElementById('userBox');
        const warn = document.getElementById('warnIncomplete');

        function maskCPF(cpf){
            if(!cpf) return cpf;
            cpf = String(cpf).replace(/\D/g,'');
            if (cpf.length !== 11) return cpf;
            return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
        }

        async function renderUserBox(){
            const token = localStorage.getItem('token');


            if (!token){
                box.innerHTML = `
                <span><strong>Sem usuário</strong></span>
                <a class="btn btn-mini" href="{{ route('login') }}">Entrar</a>
            `;
                warn.style.display = 'none';
                return;
            }

            try{
                const res = await fetch('/api/v1/me', {
                    headers: { 'Authorization': 'Bearer ' + token, 'Accept':'application/json' }
                });

                if (res.status !== 200){
                    box.innerHTML = `
                    <span><strong>Sem usuário</strong></span>
                    <a class="btn btn-mini" href="{{ route('login') }}">Entrar</a>
                `;
                    warn.style.display = 'none';
                    return;
                }

                const me = await res.json();
                const cpfMasked = maskCPF(me.cpf) || '—';

                box.innerHTML = `
                <span>Usuário: <strong>${cpfMasked}</strong></span>
                ${me.completed ? '' : '<span class="badge" style="background:#fff7d6;color:#7a5b00;border:1px solid #f7e0a3;">incompleto</span>'}
                <a class="btn btn-mini" href="{{ route('users.complete') }}">Editar Usuário</a>
                <button id="btnLogout" class="btn btn-mini btn-danger" type="button">Sair</button>
            `;


                warn.style.display = me.completed ? 'none' : 'block';


                document.getElementById('btnLogout')?.addEventListener('click', async ()=>{
                    try{
                        await fetch('/api/v1/logout', {
                            method: 'POST',
                            headers: { 'Authorization': 'Bearer ' + token, 'Accept':'application/json' }
                        });
                    }catch(_) {  }
                    localStorage.removeItem('token');
                    location.href = "{{ route('login') }}";
                });

            }catch(e){

                box.innerHTML = `
                <span><strong>Sem usuário</strong></span>
                <a class="btn btn-mini" href="{{ route('login') }}">Entrar</a>
            `;
                warn.style.display = 'none';
            }
        }

        renderUserBox();
    })();
</script>

@yield('scripts')
</body>
</html>
