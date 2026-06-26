@extends('admin.layout')

@section('title', 'Configuración de Correos')

@section('styles')
    .email-grid {
        display: grid;
        grid-template-columns: 1fr 1.5fr;
        gap: 32px;
        align-items: start;
        margin-bottom: 40px;
    }

    @media (max-width: 1024px) {
        .email-grid {
            grid-template-columns: 1fr;
        }
    }

    /* Section Card */
    .section-card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 32px;
        margin-bottom: 32px;
    }

    .section-header {
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .section-title {
        font-size: 18px;
        font-weight: 600;
        letter-spacing: -0.3px;
    }

    /* Form Styles */
    .form-group-item {
        margin-bottom: 20px;
    }

    .form-label-text {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }

    .input-field {
        width: 100%;
        background-color: var(--bg-primary);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        padding: 12px 16px;
        color: var(--text-primary);
        font-family: inherit;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .input-field:focus {
        outline: none;
        border-color: var(--accent-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
    }

    .input-field:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    /* Action Buttons */
    .btn-submit {
        background: var(--accent-gradient);
        border: none;
        color: #FFF;
        padding: 12px 24px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-submit:hover {
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        transform: translateY(-1px);
    }

    .btn-secondary {
        background-color: var(--bg-tertiary);
        border: 1px solid var(--border-color);
        color: var(--text-primary);
        padding: 12px 24px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-secondary:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .btn-edit {
        background-color: rgba(99, 102, 241, 0.08);
        color: #818CF8;
        border: 1px solid rgba(99, 102, 241, 0.2);
        padding: 8px 12px;
        border-radius: 6px;
        font-family: inherit;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-edit:hover {
        background: var(--accent-gradient);
        color: #FFF;
        border-color: transparent;
        box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
    }

    /* Errors Alert Box */
    .alert-error-container {
        background-color: rgba(239, 68, 68, 0.08);
        border: 1px solid rgba(239, 68, 68, 0.15);
        color: var(--danger-color);
        padding: 16px;
        border-radius: 12px;
        margin-bottom: 32px;
        font-size: 14px;
    }

    /* Modal Styling */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(5, 7, 12, 0.85);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 100;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
    }

    .modal-overlay.show {
        opacity: 1;
        pointer-events: auto;
    }

    .modal-card {
        background-color: var(--bg-secondary);
        border: 1px solid var(--border-color);
        border-radius: 20px;
        padding: 32px;
        width: 95%;
        max-width: 1100px;
        display: flex;
        flex-direction: column;
        max-height: 90vh;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }

    .modal-title {
        font-size: 20px;
        font-weight: 700;
        letter-spacing: -0.4px;
    }

    .modal-close {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .modal-close:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: var(--text-primary);
    }

    .modal-editor-layout {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 24px;
        overflow-y: auto;
        padding-right: 4px;
    }

    @media (max-width: 768px) {
        .modal-editor-layout {
            grid-template-columns: 1fr;
        }
    }

    .variable-container {
        background-color: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 16px;
        margin-top: 12px;
    }

    .variable-title {
        font-size: 13px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .variable-list {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .variable-row {
        display: flex;
        align-items: flex-start;
        gap: 8px;
        font-size: 13px;
    }

    .variable-chip {
        background-color: rgba(99, 102, 241, 0.08);
        border: 1px solid rgba(99, 102, 241, 0.15);
        color: #818CF8;
        padding: 2px 8px;
        border-radius: 6px;
        font-family: monospace;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
        position: relative;
    }

    .variable-chip:hover {
        background-color: var(--accent-color);
        color: #FFF;
        border-color: transparent;
    }

    .variable-desc {
        color: var(--text-secondary);
        font-size: 12px;
        margin-top: 2px;
    }

    /* Live Preview Panel */
    .preview-panel {
        display: flex;
        flex-direction: column;
        border: 1px solid var(--border-color);
        border-radius: 12px;
        overflow: hidden;
        height: 480px;
    }

    .preview-header {
        background-color: rgba(255, 255, 255, 0.02);
        padding: 12px 16px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-secondary);
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .preview-iframe {
        width: 100%;
        height: 100%;
        background-color: #f3f4f6;
        border: none;
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 24px;
        padding-top: 16px;
        border-top: 1px solid var(--border-color);
    }

    /* Table styles */
    .table-container {
        width: 100%;
        overflow-x: auto;
        border-radius: 12px;
        border: 1px solid var(--border-color);
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .admin-table th {
        background-color: rgba(255, 255, 255, 0.02);
        color: var(--text-secondary);
        font-weight: 600;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 16px 24px;
        border-bottom: 1px solid var(--border-color);
    }

    .admin-table td {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border-color);
        font-size: 14px;
        vertical-align: middle;
    }

    .admin-table tr:last-child td {
        border-bottom: none;
    }

    .client-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .client-subtext {
        font-size: 12px;
        color: var(--text-secondary);
        margin-top: 3px;
    }

    /* Toast Tooltip for Copied Variable */
    .copy-toast {
        position: fixed;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%) translateY(100px);
        background-color: #10B981;
        color: #FFF;
        padding: 10px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        font-size: 13px;
        font-weight: 600;
        z-index: 1000;
        transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .copy-toast.show {
        transform: translateX(-50%) translateY(0);
    }
@endsection

@section('content')
    <!-- Header -->
    <div class="header-section">
        <div>
            <h1 class="header-title">Configuración de Correos</h1>
            <p class="header-subtitle">Gestiona las credenciales del servidor SMTP y personaliza las plantillas de correo del sistema.</p>
        </div>
    </div>

    <!-- Error Alerts -->
    @if ($errors->any())
        <div class="alert-error-container">
            <strong>Ocurrió un error al procesar la solicitud:</strong>
            <ul style="margin-top: 8px; margin-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="email-grid">
        <!-- SMTP configuration column -->
        <div class="section-column">
            <div class="section-card">
                <h2 class="section-title" style="margin-bottom: 24px;">Servidor de Correo (SMTP)</h2>
                <form action="{{ route('admin.emails.smtp.update') }}" method="POST">
                    @csrf
                    
                    <div class="form-group-item">
                        <label class="form-label-text" for="smtp_mailer">Controlador de Envío</label>
                        <select name="smtp_mailer" id="smtp_mailer" class="input-field" onchange="toggleSmtpFields()" required>
                            <option value="log" {{ $smtp['mailer'] === 'log' ? 'selected' : '' }}>Log (Para pruebas de desarrollo local)</option>
                            <option value="smtp" {{ $smtp['mailer'] === 'smtp' ? 'selected' : '' }}>Servidor SMTP (Producción)</option>
                        </select>
                    </div>

                    <div class="smtp-fields-group">
                        <div class="form-group-item">
                            <label class="form-label-text" for="smtp_host">Servidor SMTP Host</label>
                            <input type="text" name="smtp_host" id="smtp_host" class="input-field" placeholder="smtp.mailgun.org" value="{{ $smtp['host'] }}">
                        </div>

                        <div class="form-row">
                            <div class="form-group-item">
                                <label class="form-label-text" for="smtp_port">Puerto SMTP</label>
                                <input type="number" name="smtp_port" id="smtp_port" class="input-field" placeholder="587" value="{{ $smtp['port'] }}">
                            </div>

                            <div class="form-group-item">
                                <label class="form-label-text" for="smtp_encryption">Cifrado</label>
                                <select name="smtp_encryption" id="smtp_encryption" class="input-field">
                                    <option value="none" {{ $smtp['encryption'] === 'none' ? 'selected' : '' }}>Ninguno</option>
                                    <option value="tls" {{ $smtp['encryption'] === 'tls' ? 'selected' : '' }}>TLS</option>
                                    <option value="ssl" {{ $smtp['encryption'] === 'ssl' ? 'selected' : '' }}>SSL</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group-item">
                            <label class="form-label-text" for="smtp_username">Usuario SMTP</label>
                            <input type="text" name="smtp_username" id="smtp_username" class="input-field" placeholder="postmaster@dominio.com" value="{{ $smtp['username'] }}">
                        </div>

                        <div class="form-group-item">
                            <label class="form-label-text" for="smtp_password">Contraseña SMTP</label>
                            <input type="password" name="smtp_password" id="smtp_password" class="input-field" placeholder="{{ $smtp['password'] ? '********' : 'Escribe la contraseña SMTP' }}" value="{{ $smtp['password'] }}">
                        </div>
                    </div>

                    <div class="form-group-item">
                        <label class="form-label-text" for="smtp_from_address">Email del Remitente (From)</label>
                        <input type="email" name="smtp_from_address" id="smtp_from_address" class="input-field" placeholder="hola@miempresa.com" value="{{ $smtp['from_address'] }}" required>
                    </div>

                    <div class="form-group-item">
                        <label class="form-label-text" for="smtp_from_name">Nombre del Remitente</label>
                        <input type="text" name="smtp_from_name" id="smtp_from_name" class="input-field" placeholder="DipleBill" value="{{ $smtp['from_name'] }}" required>
                    </div>

                    <button type="submit" class="btn-submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                        Guardar Configuración
                    </button>
                </form>
            </div>

            <!-- Mail Tester Section -->
            <div class="section-card">
                <h2 class="section-title" style="margin-bottom: 8px;">Probador de Correo (Mail Tester)</h2>
                <p class="form-label-text" style="margin-bottom: 20px;">Envía un correo de prueba inmediatamente con las credenciales cargadas.</p>
                <form action="{{ route('admin.emails.test') }}" method="POST">
                    @csrf
                    <div class="form-group-item">
                        <label class="form-label-text" for="test_email">Enviar Correo de Prueba A</label>
                        <input type="email" name="test_email" id="test_email" class="input-field" placeholder="mi-correo@gmail.com" required>
                    </div>
                    <button type="submit" class="btn-submit" style="background: linear-gradient(135deg, #10B981, #059669);">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                        Enviar Correo de Prueba
                    </button>
                </form>
            </div>
        </div>

        <!-- Email templates column -->
        <div class="section-column">
            <div class="section-card" style="height: 100%;">
                <h2 class="section-title" style="margin-bottom: 24px;">Plantillas del Sistema</h2>
                
                <div class="table-container">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Plantilla</th>
                                <th>Asunto</th>
                                <th style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>
                                        <div class="client-name">{{ $template->name }}</div>
                                        <div class="client-subtext" style="font-family: monospace;">{{ $template->key }}</div>
                                    </td>
                                    <td>{{ Str::limit($template->subject, 35) }}</td>
                                    <td style="text-align: right;">
                                        <button class="btn-edit" onclick="openEditTemplateModal('{{ $template->id }}')">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
                                            Editar
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="text-align: center; color: var(--text-secondary); padding: 32px;">
                                        No hay plantillas registradas.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Template -->
    <div class="modal-overlay" id="edit-template-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3 class="modal-title" id="edit-modal-title">Editar Plantilla de Correo</h3>
                <button class="modal-close" onclick="closeEditTemplateModal()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
            
            <form id="edit-template-form" method="POST">
                @csrf
                <div class="modal-editor-layout">
                    <!-- Editor Left Column -->
                    <div class="editor-col">
                        <div class="form-group-item">
                            <label class="form-label-text" for="template_subject">Asunto del Correo</label>
                            <input type="text" name="subject" id="template_subject" class="input-field" required onkeyup="updateLivePreview()">
                        </div>

                        <div class="form-group-item">
                            <label class="form-label-text" for="template_body">Contenido HTML del Correo</label>
                            <textarea name="body" id="template_body" class="input-field" rows="12" style="font-family: monospace; font-size: 13px; line-height: 1.5; resize: vertical;" required onkeyup="updateLivePreview()"></textarea>
                        </div>

                        <!-- Variables Info Box -->
                        <div class="variable-container">
                            <div class="variable-title">Variables Disponibles (Haz clic para copiar)</div>
                            <div class="variable-list" id="template-variables-list">
                                <!-- Populated dynamically by javascript -->
                            </div>
                        </div>
                    </div>

                    <!-- Editor Right Column (Live Preview) -->
                    <div class="editor-col">
                        <div class="preview-panel">
                            <div class="preview-header">
                                <span>Vista Previa del Remitente</span>
                                <span style="font-size: 11px; font-weight: normal; color: var(--text-secondary); background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;">Modo Simulado</span>
                            </div>
                            <iframe id="preview-iframe" class="preview-iframe"></iframe>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeEditTemplateModal()">Cancelar</button>
                    <button type="submit" class="btn-submit" style="width: auto;">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast message for click to copy feedback -->
    <div class="copy-toast" id="copy-toast">¡Copiado al portapapeles!</div>

    <script>
        function toggleSmtpFields() {
            const mailer = document.getElementById('smtp_mailer').value;
            const fields = document.querySelectorAll('#smtp_host, #smtp_port, #smtp_encryption, #smtp_username, #smtp_password');
            const fieldsGroup = document.querySelector('.smtp-fields-group');
            
            if (mailer === 'log') {
                fieldsGroup.style.display = 'none';
                fields.forEach(f => f.disabled = true);
            } else {
                fieldsGroup.style.display = 'block';
                fields.forEach(f => f.disabled = false);
            }
        }

        // Initialize state on page load
        document.addEventListener('DOMContentLoaded', () => {
            toggleSmtpFields();
        });

        let activeTemplate = null;

        function openEditTemplateModal(templateId) {
            const modal = document.getElementById('edit-template-modal');
            const form = document.getElementById('edit-template-form');
            
            // Set form action route URL
            form.action = `/admin/email-settings/templates/${templateId}`;

            // Fetch template details via AJAX
            fetch(`/admin/email-settings/templates/${templateId}`)
                .then(response => response.json())
                .then(data => {
                    activeTemplate = data;
                    document.getElementById('edit-modal-title').innerText = `Editar: ${data.name}`;
                    document.getElementById('template_subject').value = data.subject;
                    document.getElementById('template_body').value = data.body;

                    // Render dynamic variable list in the modal
                    const variablesList = document.getElementById('template-variables-list');
                    variablesList.innerHTML = '';

                    if (data.variables) {
                        Object.keys(data.variables).forEach(key => {
                            const desc = data.variables[key];
                            const row = document.createElement('div');
                            row.className = 'variable-row';
                            row.innerHTML = `
                                <span class="variable-chip" onclick="copyVariable('{${key}}')">{${key}}</span>
                                <span class="variable-desc">- ${desc}</span>
                            `;
                            variablesList.appendChild(row);
                        });
                    }

                    // Render the initial preview
                    updateLivePreview();
                    
                    modal.classList.add('show');
                })
                .catch(err => {
                    alert('Error al intentar cargar la plantilla de correo.');
                    console.error(err);
                });
        }

        function closeEditTemplateModal() {
            const modal = document.getElementById('edit-template-modal');
            modal.classList.remove('show');
            activeTemplate = null;
        }

        function copyVariable(variableStr) {
            navigator.clipboard.writeText(variableStr).then(() => {
                const toast = document.getElementById('copy-toast');
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                }, 2000);
            }).catch(err => {
                console.error('Fallo al copiar variable: ', err);
            });
        }

        /**
         * Update the real-time HTML view within the iframe.
         */
        function updateLivePreview() {
            const subject = document.getElementById('template_subject').value;
            let body = document.getElementById('template_body').value;
            const iframe = document.getElementById('preview-iframe');

            if (!iframe) return;

            // Simple mock data object for rendering preview placeholders
            const mockData = {
                'client_name': 'Mi Tienda S.A.',
                'owner_name': 'Carlos Rodríguez',
                'owner_email': 'propietario@mitienda.com',
                'login_url': 'https://diplebill.test/login',
                'invoice_number': 'FAC-00124',
                'issue_date': new Date().toLocaleDateString('es-ES'),
                'invoice_amount': '$4,250.00 MXN',
                'download_url': '#',
                'customer_name': 'Sofía Hernández',
                'pending_amount': '$1,500.00 MXN',
                'credit_limit': '$10,000.00 MXN',
                'due_date': new Date(Date.now() + 86400000 * 7).toLocaleDateString('es-ES') // 7 days from now
            };

            // Replace variables in the subject & body
            let renderedSubject = subject;
            let renderedBody = body;
            
            Object.keys(mockData).forEach(key => {
                const val = mockData[key];
                renderedSubject = renderedSubject.replaceAll(`{${key}}`, val);
                renderedBody = renderedBody.replaceAll(`{${key}}`, val);
            });

            // Create full HTML template mirroring emails.system layout
            const fullHtml = `
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                        background-color: #f3f4f6;
                        margin: 0;
                        padding: 20px 10px;
                        font-size: 14px;
                    }
                    .container {
                        max-width: 540px;
                        margin: 0 auto;
                        background-color: #ffffff;
                        border-radius: 8px;
                        overflow: hidden;
                        border: 1px solid #e5e7eb;
                    }
                    .header {
                        background-color: #090D16;
                        padding: 20px;
                        text-align: center;
                    }
                    .logo-icon {
                        display: inline-block;
                        width: 30px;
                        height: 30px;
                        line-height: 30px;
                        background: linear-gradient(135deg, #6366F1, #3B82F6);
                        border-radius: 6px;
                        font-weight: 700;
                        font-size: 16px;
                        color: #ffffff;
                        text-align: center;
                        vertical-align: middle;
                    }
                    .logo-text {
                        display: inline-block;
                        font-weight: 700;
                        font-size: 16px;
                        color: #ffffff;
                        margin-left: 8px;
                        vertical-align: middle;
                    }
                    .subject-header {
                        background-color: #f9fafb;
                        padding: 12px 20px;
                        font-size: 12px;
                        color: #6b7280;
                        border-bottom: 1px solid #f3f4f6;
                    }
                    .content {
                        padding: 30px;
                        line-height: 1.6;
                        color: #374151;
                    }
                    .content h1, .content h2, .content h3 {
                        color: #111827;
                        margin-top: 0;
                    }
                    .footer {
                        background-color: #f9fafb;
                        padding: 16px 20px;
                        text-align: center;
                        font-size: 11px;
                        color: #9CA3AF;
                        border-top: 1px solid #f3f4f6;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="subject-header">
                        <strong>Asunto:</strong> ${renderedSubject}
                    </div>
                    <div class="header">
                        <div class="logo-icon">D</div>
                        <div class="logo-text">DipleBill</div>
                    </div>
                    <div class="content">
                        ${renderedBody}
                    </div>
                    <div class="footer">
                        <p style="margin: 0;">Este correo electrónico fue generado de forma automática por la plataforma.</p>
                        <p style="margin: 4px 0 0 0;">&copy; ${new Date().getFullYear()} DipleBill. Todos los derechos reservados.</p>
                    </div>
                </div>
            </body>
            </html>
            `;

            // Inject the content into the iframe
            const doc = iframe.contentDocument || iframe.contentWindow.document;
            doc.open();
            doc.write(fullHtml);
            doc.close();
        }
    </script>
@endsection
