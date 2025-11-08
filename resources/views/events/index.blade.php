<!-- resources/views/events/index.blade.php -->
@extends('layouts.app')
@section('title','Eventos')
@section('h1','Eventos')
@section('content')
    <div class="toolbar">
        <form id="searchForm" class="row" onsubmit="return false">
            <input type="text" id="q" name="q" placeholder="Buscar por título, local ou descrição">
            <button class="btn">Buscar</button>
            <a class="btn btn-outline" href="{{ route('events.index') }}">Limpar</a>
        </form>
        <a class="btn btn-primary" href="{{ route('events.create') }}">+ Novo evento</a>
    </div>

    <table class="table" id="tbl">
        <thead>
        <tr>
            <th>Título</th>
            <th>Início</th>
            <th>Local</th>
            <th>Público</th>
            <th style="width:220px">Ações</th>
        </tr>
        </thead>
        <tbody></tbody>
    </table>

    <nav class="pagination" id="pager" aria-label="Paginação"></nav>
@endsection

@section('scripts')
    <script>
        (async function(){
            const $q = document.getElementById('q');
            const $tbody = document.querySelector('#tbl tbody');
            const $pager = document.getElementById('pager');

            // estado
            let page = parseInt(qs('page', 1), 10);
            let perPage = parseInt(qs('per_page', 10), 10);
            let query = qs('q','');

            $q.value = query;

            async function load(){
                const p = new URLSearchParams({ page, per_page: perPage });
                if (query) p.set('q', query);
                const res = await api(`/events?${p.toString()}`);

                // res.data é uma ResourceCollection; acesse .data.data se necessário, mas já vem "envólucro"
                const items = res.data.data ?? res.data; // compatível com ambos formatos
                const meta  = res.meta ?? {};
                const links = res.links ?? {};

                $tbody.innerHTML = '';
                items.forEach(({ id, title, location, start_at, is_public })=>{
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
        <td><a href="{{ url('/events') }}/${id}">${title}</a></td>
        <td>${start_at ? fmtDateTimeLocal(start_at).replace('T',' ') : '-'}</td>
        <td>${location ?? '-'}</td>
        <td>${is_public ? '<span class="badge green">Público</span>' : '<span class="badge gray">Privado</span>'}</td>
        <td>
          <a class="btn" href="{{ url('/events') }}/${id}">Ver</a>
          <a class="btn" href="{{ url('/events') }}/${id}/edit">Editar</a>
          <button class="btn btn-danger" data-del="${id}">Excluir</button>
        </td>
      `;
                    $tbody.appendChild(tr);
                });

                // paginação
                $pager.innerHTML = '';
                const last = meta.last_page ?? 1;
                const cur  = meta.current_page ?? 1;

                function pageLink(label,p,active=false){
                    const a = document.createElement('a');
                    a.textContent = label;
                    a.href = '#';
                    if (active) a.classList.add('current');
                    a.addEventListener('click', (e)=>{ e.preventDefault(); page=p; setQS({page, per_page:perPage, q:query}); load(); });
                    return a;
                }

                if (cur>1) $pager.appendChild(pageLink('«', 1));
                if (cur>1) $pager.appendChild(pageLink('‹', cur-1));
                for (let i=Math.max(1,cur-2); i<=Math.min(last,cur+2); i++){
                    $pager.appendChild(pageLink(String(i), i, i===cur));
                }
                if (cur<last) $pager.appendChild(pageLink('›', cur+1));
                if (cur<last) $pager.appendChild(pageLink('»', last));

                // exclusão
                document.querySelectorAll('[data-del]').forEach(btn=>{
                    btn.onclick = async ()=>{
                        if (!confirm('Excluir este evento?')) return;
                        try{
                            await api(`/events/${btn.dataset.del}`, { method:'DELETE' });
                            toast('Evento excluído.');
                            load();
                        }catch(e){ toast(e.message,false); }
                    };
                });
            }

            document.getElementById('searchForm').addEventListener('submit', (e)=>{
                e.preventDefault();
                query = $q.value.trim();
                page = 1;
                setQS({q:query, page, per_page:perPage});
                load();
            });

            load();
        })();
    </script>
@endsection
