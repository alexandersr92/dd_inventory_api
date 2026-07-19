@extends('admin.layout')

@section('title', 'Administración de Landing Page')

@section('styles')
.landing-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 32px;
}
@media (min-width: 1024px) {
    .landing-grid {
        grid-template-columns: 2fr 1fr;
    }
}
.tab-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    padding: 12px 20px;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all 0.3s ease;
}
.tab-btn.active {
    color: var(--accent-color);
    border-bottom: 2px solid var(--accent-color);
}
.tab-content {
    display: none;
    animation: fadeIn 0.4s ease forwards;
}
.tab-content.active {
    display: block;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-secondary);
    margin-bottom: 8px;
}
.form-control {
    width: 100%;
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 12px;
    font-family: inherit;
    font-size: 14px;
    color: var(--text-primary);
    outline: none;
    transition: all 0.3s ease;
}
.form-control:focus {
    border-color: var(--accent-color);
    box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
}
.btn-primary {
    background: var(--accent-gradient);
    color: #FFF;
    border: none;
    padding: 12px 24px;
    border-radius: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
}
.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(99, 102, 241, 0.3);
}
.media-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
}
.media-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent-color);
}
.copy-badge {
    cursor: pointer;
    font-size: 11px;
    background: rgba(99, 102, 241, 0.1);
    color: #818CF8;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
}
@endsection

@section('content')
<div class="header-section" style="margin-bottom: 24px;">
    <div>
        <h1 class="header-title">Administración de Landing Page</h1>
        <p class="header-subtitle">Edita los contenidos, gestiona la biblioteca de imágenes y configura los planes de suscripción públicos.</p>
    </div>
</div>

<!-- Tabs Navigation -->
<div style="border-bottom: 1px solid var(--border-color); margin-bottom: 32px; display: flex; gap: 8px;">
    <button class="tab-btn active" onclick="switchTab('sections')">Editar Contenidos (JSON)</button>
    <button class="tab-btn" onclick="switchTab('media')">Biblioteca de Imágenes</button>
    <button class="tab-btn" onclick="switchTab('plans')">Planes de Precios</button>
</div>

<!-- TAB 1: JSON SECTIONS EDITOR -->
<div id="tab-sections" class="tab-content active">
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px;">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 24px; color: var(--text-primary);">Editor de Secciones Avanzado (JSON)</h2>
        
        <div class="form-group">
            <label>Selecciona la Sección a Editar</label>
            <select id="section-selector" class="form-control" onchange="loadSectionJson()">
                <option value="hero">Cabecera (hero)</option>
                <option value="features">Características (features)</option>
                <option value="testimonials">Testimonios (testimonials)</option>
                <option value="faq">Preguntas Frecuentes (faq)</option>
                <option value="footer">Pie de Página (footer)</option>
            </select>
        </div>

        <form id="section-json-form" action="{{ route('admin.landing.content.update', 'hero') }}" method="POST" onsubmit="return validateJsonInput()">
            @csrf
            <div class="form-group">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 8px;">
                    <label style="margin:0;">Contenido JSON</label>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span id="json-live-status" style="font-size: 12px; font-weight: 600;"></span>
                        <button type="button" onclick="formatJson()" style="background: rgba(255,255,255,0.05); color: var(--text-secondary); border: 1px solid var(--border-color); padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer;">Formatear</button>
                    </div>
                </div>
                <textarea name="content_raw" id="section-json-textarea" class="form-control" oninput="liveValidateJson()" style="min-height: 350px; font-family: monospace; font-size: 13px; line-height: 1.5; color: #a5f3fc; background: rgba(0,0,0,0.3); outline: none; border-color: rgba(255,255,255,0.05);"></textarea>
                <div id="json-error-msg" style="color: var(--danger-color); font-size: 12px; margin-top: 8px; font-weight: 600; display: none;"></div>
                <p style="font-size: 12px; color: var(--text-secondary); margin-top: 8px;">Consejo: usa "Formatear" para ordenar el JSON y detectar errores. El estado (✓ válido / ✗ error) se actualiza mientras escribes. El servidor rechaza cualquier JSON inválido, así que la landing nunca se rompe por un error de sintaxis.</p>
            </div>

            <button type="submit" class="btn-primary">
                Guardar Contenido de Sección
            </button>
        </form>
    </div>
</div>

<!-- TAB 2: MEDIA LIBRARY -->
<div id="tab-media" class="tab-content">
    <div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px; margin-bottom: 32px;">
        <h2 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">Subir Nueva Imagen</h2>
        <form action="{{ route('admin.landing.media.upload') }}" method="POST" enctype="multipart/form-data" style="display: flex; flex-wrap: wrap; gap: 16px; align-items: center;">
            @csrf
            <input type="file" name="file" accept="image/*" required style="color: var(--text-secondary); font-size: 13px;">
            <button type="submit" class="btn-primary" style="padding: 10px 20px; font-size: 13px;">
                Subir Archivo
            </button>
        </form>
    </div>

    <h2 style="font-size: 18px; font-weight: 700; color: var(--text-primary); margin-bottom: 16px;">Biblioteca de Imágenes ({{ $media->count() }})</h2>
    
    @if($media->isEmpty())
        <div style="text-align: center; padding: 48px; border: 1px dashed var(--border-color); border-radius: 16px; color: var(--text-secondary);">
            No hay imágenes en la biblioteca. Sube un archivo arriba.
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px;">
            @foreach($media as $item)
                <div class="media-card">
                    <div style="height: 150px; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid var(--border-color); position: relative;">
                        <img src="{{ $item->url }}" alt="{{ $item->filename }}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                    </div>
                    <div style="padding: 12px; display: flex; flex-direction: column; gap: 8px;">
                        <div style="font-size: 12px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $item->filename }}">
                            {{ $item->filename }}
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 4px;">
                            <span class="copy-badge" onclick="copyUrl('{{ $item->url }}')">Copiar Enlace</span>
                            
                            <form action="{{ route('admin.landing.media.delete', $item->id) }}" method="POST" onsubmit="return confirm('¿Seguro de eliminar esta imagen?')">
                                @csrf
                                <button type="submit" style="background: none; border: none; color: var(--danger-color); font-size: 11px; font-weight: 600; cursor: pointer;">
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<!-- TAB 3: PRICING PLANS -->
<div id="tab-plans" class="tab-content">
    <div class="landing-grid">
        <!-- Formulario de Crear Plan -->
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 32px;">
            <h2 id="plan-form-title" style="font-size: 16px; font-weight: 600; margin-bottom: 24px; color: var(--text-primary);">Crear Plan de Suscripción</h2>
            
            <form id="plan-form" action="{{ route('admin.landing.plans.store') }}" method="POST">
                @csrf
                <div class="form-group">
                    <label>Nombre del Plan</label>
                    <input type="text" name="name" id="plan-name" class="form-control" required placeholder="Ej. Básico, Pro, Enterprise">
                </div>

                <div class="form-group">
                    <label>Precio (C$)</label>
                    <input type="number" step="0.01" name="price" id="plan-price" class="form-control" required placeholder="Ej. 19.99">
                </div>

                <div class="form-group">
                    <label>Periodo de Facturación</label>
                    <select name="period" id="plan-period" class="form-control" required>
                        <option value="monthly">Mensual</option>
                        <option value="yearly">Anual</option>
                        <option value="lifetime">De por vida</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Porcentaje de Descuento (%)</label>
                    <input type="number" step="0.1" name="discount" id="plan-discount" class="form-control" value="0.0" placeholder="Ej. 10.0">
                </div>

                <div class="form-group">
                    <label>Beneficios / Características (Uno por línea)</label>
                    <textarea name="features_raw" id="plan-features" class="form-control" style="min-height: 120px; resize: vertical;" placeholder="Soporte 24/7&#10;Facturas ilimitadas&#10;Control de Inventario"></textarea>
                </div>

                <div class="form-group" style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="is_featured" id="plan-is-featured" value="1">
                    <label for="plan-is-featured" style="margin-bottom: 0; cursor: pointer;">Destacar este Plan (Featured)</label>
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select name="status" id="plan-status" class="form-control">
                        <option value="active">Activo (Público)</option>
                        <option value="inactive">Inactivo (Oculto)</option>
                    </select>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="submit" class="btn-primary" id="plan-submit-btn">Crear Plan</button>
                    <button type="button" class="btn-primary" style="background: rgba(255,255,255,0.05); color: var(--text-secondary); border: 1px solid var(--border-color); box-shadow: none;" onclick="resetPlanForm()">
                        Cancelar Edición
                    </button>
                </div>
            </form>
        </div>

        <!-- Tabla de Planes Existentes -->
        <div style="background-color: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 16px; padding: 24px;">
            <h3 style="font-size: 14px; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">Planes Registrados</h3>
            
            @if($plans->isEmpty())
                <div style="text-align: center; padding: 24px; color: var(--text-secondary); font-size: 13px;">
                    No hay planes registrados.
                </div>
            @else
                <div style="display: flex; flex-direction: column; gap: 12px;">
                    @foreach($plans as $plan)
                        <div style="padding: 16px; border: 1px solid {{ $plan->is_featured ? 'var(--accent-color)' : 'var(--border-color)' }}; border-radius: 12px; background: rgba(0,0,0,0.1);">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                                <div>
                                    <span style="font-weight: 700; color: #fff; font-size: 15px;">{{ $plan->name }}</span>
                                    @if($plan->is_featured)
                                        <span style="font-size: 10px; background: var(--accent-gradient); color: #fff; padding: 2px 6px; border-radius: 10px; font-weight: 700; margin-left: 6px; text-transform: uppercase;">Top</span>
                                    @endif
                                </div>
                                <div style="font-weight: 800; color: var(--accent-color);">
                                    C$ {{ $plan->price }} <span style="font-size: 11px; color: var(--text-secondary); font-weight: 500;">/ {{ $plan->period }}</span>
                                </div>
                            </div>
                            
                            @if($plan->discount > 0)
                                <div style="font-size: 11px; color: var(--success-color); font-weight: 600; margin-bottom: 8px;">
                                    Descuento: {{ $plan->discount }}%
                                </div>
                            @endif

                            <ul style="font-size: 12px; color: var(--text-secondary); list-style: inside circle; margin-bottom: 16px; display: flex; flex-direction: column; gap: 4px; padding-left: 4px;">
                                @foreach($plan->features as $feat)
                                    <li>{{ $feat }}</li>
                                @endforeach
                            </ul>

                            <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border-color); padding-top: 12px; margin-top: 12px;">
                                <button type="button" style="background: none; border: none; color: #818CF8; font-size: 12px; font-weight: 600; cursor: pointer;" 
                                    onclick="editPlan({
                                        id: '{{ $plan->id }}',
                                        name: '{{ $plan->name }}',
                                        price: '{{ $plan->price }}',
                                        period: '{{ $plan->period }}',
                                        discount: '{{ $plan->discount }}',
                                        is_featured: {{ $plan->is_featured ? 1 : 0 }},
                                        status: '{{ $plan->status }}',
                                        features: {!! json_encode($plan->features) !!}
                                    })">
                                    Editar
                                </button>
                                
                                <form action="{{ route('admin.landing.plans.delete', $plan->id) }}" method="POST" onsubmit="return confirm('¿Seguro de eliminar este plan?')">
                                    @csrf
                                    <button type="submit" style="background: none; border: none; color: var(--danger-color); font-size: 12px; font-weight: 600; cursor: pointer;">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

<script>
    // Manejo de pestañas (Tabs)
    function switchTab(tabId) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        event.currentTarget.classList.add('active');
        document.getElementById('tab-' + tabId).classList.add('active');
    }

    // Copiar URL al portapapeles
    function copyUrl(url) {
        navigator.clipboard.writeText(url).then(() => {
            // Mostrar una alerta/toast
            const originalBadge = event.target;
            originalBadge.innerText = '¡Copiado!';
            originalBadge.style.color = '#10B981';
            originalBadge.style.background = 'rgba(16, 185, 129, 0.1)';
            setTimeout(() => {
                originalBadge.innerText = 'Copiar Enlace';
                originalBadge.style.color = '#818CF8';
                originalBadge.style.background = 'rgba(99, 102, 241, 0.1)';
            }, 2000);
        });
    }

    // Editar Plan en el Formulario
    function editPlan(plan) {
        document.getElementById('plan-form-title').innerText = 'Editar Plan de Suscripción: ' + plan.name;
        document.getElementById('plan-submit-btn').innerText = 'Actualizar Plan';
        
        // Cambiar la ruta del formulario
        document.getElementById('plan-form').action = '/admin/landing/plans/' + plan.id + '/update';
        
        // Rellenar campos
        document.getElementById('plan-name').value = plan.name;
        document.getElementById('plan-price').value = plan.price;
        document.getElementById('plan-period').value = plan.period;
        document.getElementById('plan-discount').value = plan.discount;
        document.getElementById('plan-is-featured').checked = plan.is_featured === 1;
        document.getElementById('plan-status').value = plan.status;
        
        // Features array a raw string
        if (plan.features) {
            document.getElementById('plan-features').value = plan.features.join('\n');
        } else {
            document.getElementById('plan-features').value = '';
        }

        // Hacer scroll hasta el formulario
        document.getElementById('plan-form').scrollIntoView({ behavior: 'smooth' });
    }

    // Resetear formulario a modo creación
    function resetPlanForm() {
        document.getElementById('plan-form-title').innerText = 'Crear Plan de Suscripción';
        document.getElementById('plan-submit-btn').innerText = 'Crear Plan';
        document.getElementById('plan-form').action = '{{ route("admin.landing.plans.store") }}';
        
        document.getElementById('plan-name').value = '';
        document.getElementById('plan-price').value = '';
        document.getElementById('plan-period').value = 'monthly';
        document.getElementById('plan-discount').value = '0.0';
        document.getElementById('plan-is-featured').checked = false;
        document.getElementById('plan-status').value = 'active';
        document.getElementById('plan-features').value = '';
    }

    // Datos de las secciones precargadas para el editor JSON
    const rawSections = {!! json_encode($sections) !!};

    function loadSectionJson() {
        const key = document.getElementById('section-selector').value;
        const textarea = document.getElementById('section-json-textarea');
        const form = document.getElementById('section-json-form');
        
        // Cargar el JSON correspondiente
        textarea.value = rawSections[key] || '{}';
        
        // Actualizar la ruta del formulario
        form.action = '/admin/landing/content/' + key;
        
        // Limpiar mensajes de error
        document.getElementById('json-error-msg').style.display = 'none';
    }

    function validateJsonInput() {
        const textarea = document.getElementById('section-json-textarea');
        const errorDiv = document.getElementById('json-error-msg');
        try {
            JSON.parse(textarea.value);
            errorDiv.style.display = 'none';
            return true;
        } catch (e) {
            errorDiv.innerText = 'Error de sintaxis JSON: ' + e.message;
            errorDiv.style.display = 'block';
            textarea.scrollIntoView({ behavior: 'smooth' });
            return false;
        }
    }

    // Validación en vivo mientras se escribe: muestra ✓ / ✗ sin bloquear.
    function liveValidateJson() {
        const textarea = document.getElementById('section-json-textarea');
        const status = document.getElementById('json-live-status');
        try {
            JSON.parse(textarea.value);
            status.textContent = '✓ JSON válido';
            status.style.color = '#34D399';
        } catch (e) {
            status.textContent = '✗ JSON con error';
            status.style.color = 'var(--danger-color)';
        }
    }

    // Ordena e indenta el JSON; si es inválido, avisa sin perder el texto.
    function formatJson() {
        const textarea = document.getElementById('section-json-textarea');
        try {
            textarea.value = JSON.stringify(JSON.parse(textarea.value), null, 2);
            liveValidateJson();
        } catch (e) {
            const errorDiv = document.getElementById('json-error-msg');
            errorDiv.innerText = 'No se puede formatear: ' + e.message;
            errorDiv.style.display = 'block';
        }
    }

    // Inicializar el JSON del editor al cargar
    document.addEventListener('DOMContentLoaded', () => {
        loadSectionJson();
        liveValidateJson();
    });
</script>
@endsection
