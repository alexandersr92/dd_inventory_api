@extends('admin.layout')

@section('title', 'Versiones y novedades')

@section('content')
<div class="header-section" style="margin-bottom: 32px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 28px; font-weight: 800; letter-spacing: -0.5px;">Versiones y novedades</h1>
        <p style="color: var(--text-secondary); margin-top: 8px;">Publica y administra los anuncios de nuevas versiones y mejoras para los usuarios de DipleBill.</p>
    </div>
    <button onclick="openReleaseForm()" style="background: var(--accent-gradient); color: #fff; border: none; padding: 12px 20px; border-radius: 10px; font-weight: 600; cursor: pointer;">+ Nueva versión</button>
</div>

@if(session('success'))
    <div style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; color: var(--success-color); font-size: 14px; font-weight: 500;">{{ session('success') }}</div>
@endif
@if($errors->any())
    <div style="background: rgba(239,68,68,0.08); border: 1px solid rgba(239,68,68,0.2); border-radius: 10px; padding: 14px 18px; margin-bottom: 24px; color: var(--danger-color); font-size: 14px;">{{ $errors->first() }}</div>
@endif

<div style="display: flex; flex-direction: column; gap: 16px;">
    @forelse($releases as $release)
        <div style="background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 12px; flex-wrap: wrap; gap: 12px;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <span style="font-size: 14px; font-weight: 700; background: rgba(99,102,241,0.15); color: #818CF8; padding: 4px 10px; border-radius: 8px;">v{{ $release->version }}</span>
                        <h3 style="font-size: 18px; font-weight: 700; margin: 0;">{{ $release->title }}</h3>
                    </div>
                    <p style="color: var(--text-secondary); font-size: 13px; margin-top: 6px;">
                        @if($release->published_at)
                            Publicado el: <strong style="color: var(--text-primary);">{{ $release->published_at->format('d/m/Y H:i') }}</strong>
                        @else
                            <span style="color: var(--text-secondary); font-style: italic;">No publicado (borrador preparado)</span>
                        @endif
                        &nbsp;·&nbsp; Creado: {{ $release->created_at->format('d/m/Y') }}
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    @if($release->published_at)
                        <span style="font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; background: rgba(16,185,129,0.15); color:#34D399; border: 1px solid rgba(16,185,129,0.3);">
                            ● Publicado
                        </span>
                    @else
                        <span style="font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 6px; background: rgba(245,158,11,0.15); color:#FBBF24; border: 1px solid rgba(245,158,11,0.3);">
                            ○ No publicado
                        </span>
                    @endif
                </div>
            </div>

            <div style="background: rgba(255,255,255,0.02); border: 1px solid var(--border-color); border-radius: 10px; padding: 14px 16px; margin: 16px 0;">
                <p style="font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-secondary); margin-bottom: 8px;">Cambios anunciados:</p>
                <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: var(--text-primary); line-height: 1.6;">
                    @foreach(explode("\n", $release->body) as $line)
                        @if(trim($line) !== '')
                            <li>{{ trim($line) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>

            <div style="display:flex; justify-content: flex-end; gap:8px; flex-wrap: wrap;">
                <form action="{{ route('admin.releases.toggle', $release->id) }}" method="POST">
                    @csrf
                    @if($release->published_at)
                        <button type="submit" style="background: rgba(245,158,11,0.1); color: #FBBF24; border: 1px solid rgba(245,158,11,0.4); padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Retirar (Despublicar)
                        </button>
                    @else
                        <button type="submit" style="background: rgba(16,185,129,0.1); color: #34D399; border: 1px solid rgba(16,185,129,0.4); padding: 8px 14px; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Publicar ahora
                        </button>
                    @endif
                </form>

                <button onclick='editRelease(@json($release))' style="background: rgba(99,102,241,0.1); color:#818CF8; border:1px solid rgba(99,102,241,0.4); padding: 8px 14px; border-radius: 8px; font-weight:600; cursor:pointer;">
                    Editar
                </button>

                <form action="{{ route('admin.releases.destroy', $release->id) }}" method="POST" onsubmit="return confirm('¿Eliminar la publicación v{{ $release->version }}?')" style="margin:0;">
                    @csrf
                    <button type="submit" style="background: rgba(239,68,68,0.1); color: var(--danger-color); border:1px solid rgba(239,68,68,0.3); padding: 8px 14px; border-radius: 8px; font-weight:600; cursor:pointer;">
                        Eliminar
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div style="background: var(--bg-secondary); border: 1px dashed var(--border-color); border-radius: 16px; padding: 48px; text-align: center;">
            <p style="color: var(--text-secondary); font-size: 15px; margin-bottom: 16px;">Todavía no hay publicaciones registradas.</p>
            <button onclick="openReleaseForm()" style="background: var(--accent-gradient); color: #fff; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; cursor: pointer;">+ Crear la primera publicación</button>
        </div>
    @endforelse
</div>

<!-- Modal formulario -->
<div id="releaseModal" style="display:none; position:fixed; inset:0; background: rgba(0,0,0,0.6); z-index:100; align-items:center; justify-content:center;">
    <div style="background: var(--bg-secondary); border:1px solid var(--border-color); border-radius:16px; padding:28px; width:100%; max-width:540px; max-height:90vh; overflow:auto;">
        <h2 id="releaseModalTitle" style="font-size:18px; font-weight:700; margin-bottom:20px;">Nueva versión</h2>
        <form id="releaseForm" method="POST">
            @csrf
            @php
                $fld = 'width:100%; background: rgba(255,255,255,0.03); border:1px solid var(--border-color); border-radius:8px; padding:10px 12px; font-size:14px; color: var(--text-primary); outline:none; box-sizing: border-box;';
                $lbl = 'display:block; font-size:13px; font-weight:500; margin-bottom:6px; color: var(--text-primary);';
            @endphp
            <div style="margin-bottom:16px;">
                <label style="{{ $lbl }}">Versión (etiqueta)</label>
                <input name="version" id="r_version" required placeholder="Ej. 1.5.0" style="{{ $fld }}">
                <span style="font-size: 12px; color: var(--text-secondary); margin-top: 4px; display: block;">Etiqueta informativa de la publicación de novedades.</span>
            </div>
            <div style="margin-bottom:16px;">
                <label style="{{ $lbl }}">Título del anuncio</label>
                <input name="title" id="r_title" required placeholder="Ej. Mejoras en facturación y sesión" style="{{ $fld }}">
            </div>
            <div style="margin-bottom:16px;">
                <label style="{{ $lbl }}">Cambios (un cambio por línea)</label>
                <textarea name="body" id="r_body" rows="6" required placeholder="- Ahora podés facturar conceptos libres.&#10;- Podés mantener iniciada la sesión del vendedor.&#10;- Proformas y borradores se pueden activar desde Configuración." style="{{ $fld }} resize: vertical; line-height: 1.5;"></textarea>
            </div>
            <div id="publishNowContainer" style="margin-bottom:20px; display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="publish_now" id="r_publish_now" value="1" style="cursor: pointer; width: 16px; height: 16px;">
                <label for="r_publish_now" style="font-size: 13px; color: var(--text-primary); cursor: pointer; user-select: none;">
                    Publicar de inmediato (si no se marca, se guarda como borrador no publicado)
                </label>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top: 24px;">
                <button type="button" onclick="closeReleaseForm()" style="background:transparent; border:1px solid var(--border-color); color:var(--text-secondary); padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer;">Cancelar</button>
                <button type="submit" style="background:var(--accent-gradient); color:#fff; border:none; padding:10px 22px; border-radius:8px; font-weight:600; cursor:pointer;">Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReleaseForm() {
    document.getElementById('releaseModalTitle').textContent = 'Nueva versión y novedades';
    document.getElementById('releaseForm').action = "{{ route('admin.releases.store') }}";
    document.getElementById('r_version').value = '';
    document.getElementById('r_title').value = '';
    document.getElementById('r_body').value = '';
    document.getElementById('publishNowContainer').style.display = 'flex';
    document.getElementById('r_publish_now').checked = false;
    document.getElementById('releaseModal').style.display = 'flex';
}

function editRelease(release) {
    document.getElementById('releaseModalTitle').textContent = 'Editar versión ' + release.version;
    document.getElementById('releaseForm').action = "/admin/releases/" + release.id + "/update";
    document.getElementById('r_version').value = release.version;
    document.getElementById('r_title').value = release.title;
    document.getElementById('r_body').value = release.body;
    document.getElementById('publishNowContainer').style.display = 'none';
    document.getElementById('releaseModal').style.display = 'flex';
}

function closeReleaseForm() {
    document.getElementById('releaseModal').style.display = 'none';
}
</script>
@endsection
