@extends('layouts.app')
@section('title','Eventos')
@section('h1','Eventos')

@section('content')
    <div id="alert" class="alert d-none"></div>

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
        (function(){
            // --------- Fallback helpers (caso não existam globais) ----------
            function $_(sel, ctx=document){ return ctx.querySelector(sel); }
            function $$_(sel, ctx=document){ return Array.from(ctx.querySelectorAll(sel)); }
            function qs(key, def=''){
                const u = new URL(location.href);
                return u.searchParams.get(key) ?? def;
            }
            function setQS(obj){
                const u = new URL(location.href);
                Object.entries(obj).forEach(([k,v])=>{
                    if (v===undefined || v===null || v==='') u.searchParams.delete(k);
                    else u.searchParams.set(k, v);
                });
                history.replaceState({}, '', u);
            }
            function toast(msg, ok=true){
                // usa alerta da página se existir; se você já tem toast global, pode remover isto
                const box = $_('#alert');
                box.className = 'alert ' + (ok ? 'alert-success' : 'alert-danger');
                box.textContent = msg;
                box.classList.remove('d-none');
                setTimeout(()=> box.classList.add('d-none'), 2000);
            }
            function fmtDateTimeLocal(str){
                // espera ISO (ex.: 2025-11-09T13:00:00Z ou sem Z)
                if (!str) return '';
                // tira segundos e fuso para caber no input/diplay
                const s = String(str).replace('Z','').slice(0,16);
                return s;
            }
            async function getJSON(url, opts={}){
                const res = await fetch(url, { headers: { 'Accept': 'application/json' }, ...opts });
                const json = await res.json().catch(()=> ({}));
                return { res, json };
            }
            function extractItems(payload){
                // seu EventController retorna: { data: EventResource::collection($events), meta, links }
                // ResourceCollection costuma virar algo como { data: [ ... ] }
                // então pode vir:
                // - payload.data.data (muito comum quando se encapa 2x)
                // - payload.data (collection "plana")
                // - payload (se você já normalizou)
                if (payload?.data?.data) return payload.data.data;
                if (Array.isArray(payload?.data)) return payload.data;
                if (Array.isArray(payload)) return payload;
                return [];
            }

            // ------------------ DOM refs & estado -------------------
            const $q      = $_('#q');
            const $tbody  = $_('#tbl tbody');
            const $pager  = $_('#pager');

            let page    = parseInt(qs('page', 1), 10);
            let perPage = parseInt(qs('per_page', 10), 10);
            let query   = qs('q','');

            $q.value = query;

            // ------------------ Carregar lista ----------------------
            async function load(){
                const p = new URLSearchParams({ page, per_page: perPage });
                if (query) p.set('q', query);

                const { res, json } = await getJSON(`/api/v1/events?${p.toString()}`);
                if (!res.ok){
                    toast(json.message || 'Falha ao carregar eventos.', false);
                    return;
                }

                const items = extractItems(json);
                const meta  = json.meta ?? {};
                // const links = json.links ?? {}; // se precisar

                $tbody.innerHTML = '';
                items.forEach((e) => {
                    const id        = e.id;
                    const title     = e.title ?? '-';
                    const location  = e.location ?? '-';
                    const start_at  = e.start_at ? fmtDateTimeLocal(e.start_at).replace('T',' ') : '-';
                    const is_public = e.is_public ? true : false; // se não existir no model, ficará false (ok)

                    const tr = document.createElement('tr');
                    tr.setAttribute('data-id-row', id);
                    tr.innerHTML = `
        <td><a href="{{ url('/events') }}/${id}">${title}</a></td>
        <td>${start_at}</td>
        <td>${location}</td>
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
                buildPager(meta);
                bindDeleteButtons();
            }

            function buildPager(meta){
                $pager.innerHTML = '';
                const last = meta.last_page ?? 1;
                const cur  = meta.current_page ?? 1;

                function pageLink(label,p,active=false){
                    const a = document.createElement('a');
                    a.textContent = label;
                    a.href = '#';
                    if (active) a.classList.add('current');
                    a.addEventListener('click', (e)=>{
                        e.preventDefault();
                        page = p;
                        setQS({ page, per_page: perPage, q: query });
                        load();
                    });
                    return a;
                }

                if (cur>1) $pager.appendChild(pageLink('«', 1));
                if (cur>1) $pager.appendChild(pageLink('‹', cur-1));
                for (let i=Math.max(1,cur-2); i<=Math.min(last,cur+2); i++){
                    $pager.appendChild(pageLink(String(i), i, i===cur));
                }
                if (cur<last) $pager.appendChild(pageLink('›', cur+1));
                if (cur<last) $pager.appendChild(pageLink('»', last));
            }

            function bindDeleteButtons(){
                $$_('[data-del]').forEach(btn=>{
                    btn.onclick = async ()=>{
                        const id = btn.dataset.del;
                        if (!confirm('Excluir este evento?')) return;
                        const token = localStorage.getItem('token');
                        if(!token) { toast('Sessão expirada. Faça login novamente.', false); return; }

                        try{
                            const res = await fetch(`/api/v1/events/${id}`, {
                                method: 'DELETE',
                                headers: {
                                    'Authorization': 'Bearer ' + token,
                                    'Accept': 'application/json'
                                }
                            });
                            const data = await res.json().catch(()=> ({}));

                            if (res.ok){
                                const row = $_(`[data-id-row="${id}"]`);
                                if (row) row.remove();
                                toast('Evento excluído.');
                            }else if(res.status === 401){
                                toast('Sessão expirada. Faça login novamente.', false);
                            }else{
                                toast(data.message || 'Erro ao excluir.', false);
                            }
                        }catch(_){
                            toast('Erro de rede ao excluir.', false);
                        }
                    };
                });
            }

            // busca
            $_('#searchForm').addEventListener('submit', (e)=>{
                e.preventDefault();
                query = $q.value.trim();
                page  = 1;
                setQS({ q: query, page, per_page: perPage });
                load();
            });

            // inicial
            load();
        })();
    </script>
@endsection
