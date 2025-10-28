(function() {
    'use strict';
    
    const API = '/ui/seo/seo_api.php';
    
    function createModal() {
        const modal = document.createElement('div');
        modal.className = 'seo-settings-modal';
        modal.id = 'seoSettingsModal';
        modal.innerHTML = `
            <div class="seo-settings-content">
                <div class="seo-settings-header">
                    <h2>⚙️ Настройки SEO</h2>
                    <button class="seo-close" onclick="closeSeoSettings()">×</button>
                </div>
                
                <form id="seoSettingsForm">
                    <div class="seo-section">
                        <h3>🌐 Основные настройки</h3>
                        
                        <div class="seo-form-group">
                            <label>Название сайта</label>
                            <input type="text" name="site_name" placeholder="My Awesome Website">
                        </div>
                        
                        <div class="seo-form-group">
                            <label>Описание сайта</label>
                            <textarea name="site_description" placeholder="Краткое описание вашего сайта"></textarea>
                        </div>
                        
                        <div class="seo-image-group">
                            <label class="seo-image-label">Изображение по умолчанию (для соц. сетей)</label>
                            <div class="seo-hint">Рекомендуемый размер: 1200×630 пикселей</div>
                            <div class="seo-image-upload">
                                <label class="seo-upload-btn">
                                    📷 Загрузить изображение
                                    <input type="file" accept="image/*" onchange="handleImageUpload(this, 'default_image')">
                                </label>
                            </div>
                            <div class="seo-image-preview" id="preview_default_image">
                                <img src="" alt="Preview">
                                <div class="seo-image-preview-actions">
                                    <span class="seo-image-url"></span>
                                    <button type="button" class="seo-btn-delete" onclick="deleteImage('default_image')">🗑 Удалить</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seo-section">
                        <h3>🏢 Организация</h3>
                        
                        <div class="seo-form-group">
                            <label>Название организации</label>
                            <input type="text" name="organization_name" placeholder="Company Name">
                        </div>
                        
                        <div class="seo-image-group">
                            <label class="seo-image-label">Логотип организации</label>
                            <div class="seo-hint">Рекомендуемый размер: квадратный, минимум 400×400 пикселей</div>
                            <div class="seo-image-upload">
                                <label class="seo-upload-btn">
                                    📷 Загрузить логотип
                                    <input type="file" accept="image/*" onchange="handleImageUpload(this, 'organization_logo')">
                                </label>
                            </div>
                            <div class="seo-image-preview" id="preview_organization_logo">
                                <img src="" alt="Preview">
                                <div class="seo-image-preview-actions">
                                    <span class="seo-image-url"></span>
                                    <button type="button" class="seo-btn-delete" onclick="deleteImage('organization_logo')">🗑 Удалить</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="seo-section">
                        <h3>🐦 Twitter</h3>
                        
                        <div class="seo-form-group">
                            <label>Twitter handle (без @)</label>
                            <input type="text" name="twitter_handle" placeholder="username">
                        </div>
                    </div>
                    
                    <div class="seo-section">
                        <h3>🔧 Дополнительно</h3>
                        
                        <div class="seo-checkbox-group">
                            <input type="checkbox" id="enable_json_ld" name="enable_json_ld" checked>
                            <label for="enable_json_ld">Включить JSON-LD (структурированные данные)</label>
                        </div>
                        
                        <div class="seo-checkbox-group">
                            <input type="checkbox" id="enable_og" name="enable_og" checked>
                            <label for="enable_og">Включить Open Graph метатеги</label>
                        </div>
                        
                        <div class="seo-checkbox-group">
                            <input type="checkbox" id="enable_twitter" name="enable_twitter" checked>
                            <label for="enable_twitter">Включить Twitter Card метатеги</label>
                        </div>
                    </div>
                    
                    <div class="seo-buttons">
                        <button type="submit" class="seo-btn primary">💾 Сохранить</button>
                        <button type="button" class="seo-btn" onclick="closeSeoSettings()">Отмена</button>
                    </div>
                </form>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Закрытие по клику на фон
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeSeoSettings();
            }
        });
        
        // Обработка формы
        const form = modal.querySelector('#seoSettingsForm');
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await saveSeoSettings(form);
        });
    }
    
    async function loadSeoSettings() {
        try {
            const response = await fetch(API + '?action=getSettings');
            const data = await response.json();
            
            if (data.ok) {
                const form = document.getElementById('seoSettingsForm');
                const settings = data.settings || {};
                
                // Заполняем текстовые поля
                Object.keys(settings).forEach(key => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = settings[key] === '1' || settings[key] === 'true';
                        } else {
                            input.value = settings[key];
                        }
                    }
                });
                
                // Показываем превью изображений
                ['default_image', 'organization_logo', 'favicon'].forEach(type => {
                    if (settings[type]) {
                        showImagePreview(type, settings[type]);
                    }
                });
            }
        } catch (error) {
            console.error('Ошибка загрузки настроек SEO:', error);
        }
    }
    
    function showImagePreview(type, url) {
        const preview = document.getElementById('preview_' + type);
        if (preview) {
            preview.classList.add('show');
            preview.querySelector('img').src = url;
            preview.querySelector('.seo-image-url').textContent = url;
        }
    }
    
    window.handleImageUpload = async function(input, type) {
        if (!input.files || !input.files[0]) return;
        
        const file = input.files[0];
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', type);
        
        // Показываем индикатор загрузки
        const btn = input.closest('.seo-upload-btn');
        const originalText = btn.childNodes[0].textContent;
        btn.childNodes[0].textContent = '⏳ Загрузка...';
        btn.style.pointerEvents = 'none';
        
        try {
            const response = await fetch(API + '?action=uploadImage', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.ok) {
                showImagePreview(type, data.url);
                alert('✅ Изображение загружено!');
            } else {
                alert('❌ Ошибка: ' + (data.error || 'Не удалось загрузить'));
            }
        } catch (error) {
            console.error('Ошибка загрузки:', error);
            alert('❌ Ошибка загрузки изображения');
        } finally {
            btn.childNodes[0].textContent = originalText;
            btn.style.pointerEvents = '';
            input.value = ''; // Сброс input
        }
    };
    
    window.deleteImage = async function(type) {
        if (!confirm('Удалить изображение?')) return;
        
        try {
            const formData = new FormData();
            formData.append('type', type);
            
            const response = await fetch(API + '?action=deleteImage', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.ok) {
                const preview = document.getElementById('preview_' + type);
                if (preview) {
                    preview.classList.remove('show');
                    preview.querySelector('img').src = '';
                    preview.querySelector('.seo-image-url').textContent = '';
                }
                alert('✅ Изображение удалено');
            } else {
                alert('❌ Ошибка удаления');
            }
        } catch (error) {
            console.error('Ошибка удаления:', error);
            alert('❌ Ошибка удаления изображения');
        }
    };
    
    async function saveSeoSettings(form) {
        const formData = new FormData(form);
        const settings = {};
        
        for (let [key, value] of formData.entries()) {
            const input = form.querySelector(`[name="${key}"]`);
            if (input && input.type === 'checkbox') {
                settings[key] = input.checked ? '1' : '0';
            } else {
                settings[key] = value;
            }
        }
        
        try {
            const response = await fetch(API + '?action=saveSettings', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(settings)
            });
            
            const data = await response.json();
            
            if (data.ok) {
                alert('✅ Настройки SEO сохранены!');
                closeSeoSettings();
            } else {
                alert('❌ Ошибка: ' + (data.error || 'Неизвестная ошибка'));
            }
        } catch (error) {
            console.error('Ошибка сохранения настроек SEO:', error);
            alert('❌ Ошибка сохранения настроек');
        }
    }
    
    window.openSeoSettings = function() {
        let modal = document.getElementById('seoSettingsModal');
        if (!modal) {
            createModal();
            modal = document.getElementById('seoSettingsModal');
        }
        modal.classList.add('show');
        loadSeoSettings();
    };
    
    window.closeSeoSettings = function() {
        const modal = document.getElementById('seoSettingsModal');
        if (modal) {
            modal.classList.remove('show');
        }
    };
    
    // Добавляем кнопку в топбар редактора
    function addSeoButton() {
        const topbar = document.querySelector('.topbar');
        if (!topbar || document.getElementById('btnSeoSettings')) return;
        
        const btn = document.createElement('button');
        btn.id = 'btnSeoSettings';
        btn.className = 'btn';
        btn.textContent = '⚙️ SEO';
        btn.onclick = openSeoSettings;
        
        // Вставляем после кнопки "Экспорт"
        const exportBtn = document.getElementById('btnExport');
        if (exportBtn && exportBtn.nextSibling) {
            topbar.insertBefore(btn, exportBtn.nextSibling);
        } else {
            topbar.appendChild(btn);
        }
    }
    
    // Инициализация
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addSeoButton);
    } else {
        addSeoButton();
    }
})();