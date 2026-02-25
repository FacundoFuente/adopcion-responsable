import './styles/homepage.css';

document.addEventListener('DOMContentLoaded', function () {
  const homepageApp = document.getElementById('homepageApp');
  const dniInput = document.getElementById('dni');
  const modal = document.getElementById('personModal');
  const searchForm = document.getElementById('searchForm');
  const searchBtn = document.getElementById('searchButton');
  const searchCardWrapper = document.getElementById('searchCardWrapper');
  const showSearchCardWrapper = document.getElementById('showSearchCardWrapper');
  const showSearchCardBtn = document.getElementById('showSearchCardBtn');
  const goToAddEntryBtn = document.getElementById('goToAddEntryBtn');
  const searchFeedback = document.getElementById('searchFeedback');
  const actionFeedback = document.getElementById('actionFeedback');
  const homepageInfoRow = document.getElementById('homepageInfoRow');
  const signInUrl = '/sign-in';
  const registerUrl = '/register';
  const isAuthenticated = homepageApp?.dataset.isAuthenticated === '1';

  let searchInProgress = false;

  function setMobileAddEntryVisible(isVisible) {
    if (!goToAddEntryBtn) {
      return;
    }

    goToAddEntryBtn.classList.toggle('d-none', !isVisible);
  }

  function scrollToAddEntryForm() {
    const input = document.getElementById('personDescription');
    if (!input) {
      return;
    }

    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
    window.setTimeout(() => {
      input.focus({ preventScroll: true });
    }, 220);
  }

  function setHomepageInfoVisible(isVisible) {
    if (!homepageInfoRow) {
      return;
    }

    homepageInfoRow.classList.toggle('d-none', !isVisible);
  }

  function showSearchCard() {
    searchCardWrapper.classList.remove('d-none');
    showSearchCardWrapper.classList.add('d-none');
    setHomepageInfoVisible(true);
    setMobileAddEntryVisible(false);
    modal.classList.remove('prontuario-shell');
    modal.innerHTML = '';
    clearFeedback(searchFeedback);
    clearFeedback(actionFeedback);
    dniInput.value = '';
    dniInput.classList.remove('is-invalid');
    dniInput.focus();
  }

  function showSearchShortcut() {
    searchCardWrapper.classList.add('d-none');
    showSearchCardWrapper.classList.remove('d-none');
    setHomepageInfoVisible(false);
  }

  showSearchCardBtn.addEventListener('click', showSearchCard);
  goToAddEntryBtn?.addEventListener('click', scrollToAddEntryForm);

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function normalizeDni(value) {
    return String(value ?? '')
      .replace(/\D+/g, '')
      .slice(0, 8);
  }

  function isValidDni(dni) {
    return /^\d{7,8}$/.test(dni);
  }

  function buildSignInUrlWithReturnTo(dni) {
    if (!isValidDni(dni)) {
      return signInUrl;
    }

    const returnTo = `/?dni=${encodeURIComponent(dni)}`;
    return `${signInUrl}?return_to=${encodeURIComponent(returnTo)}`;
  }

  function buildRegisterUrlWithReturnTo(dni) {
    if (!isValidDni(dni)) {
      return registerUrl;
    }

    const returnTo = `/?dni=${encodeURIComponent(dni)}`;
    return `${registerUrl}?return_to=${encodeURIComponent(returnTo)}`;
  }

  function clearFeedback(container) {
    if (!container) {
      return;
    }

    const timeoutId = Number(container.dataset.timeoutId || 0);
    if (timeoutId) {
      window.clearTimeout(timeoutId);
      delete container.dataset.timeoutId;
    }
    container.innerHTML = '';
  }

  function renderFeedback(container, type, message, autoHideMs = 0) {
    if (!container) {
      return;
    }

    clearFeedback(container);
    container.innerHTML = `
      <div class="alert alert-${type} alert-dismissible mb-0" role="alert">
        ${escapeHtml(message)}
        <button type="button" class="btn-close" aria-label="Cerrar"></button>
      </div>
    `;

    const closeButton = container.querySelector('.btn-close');
    closeButton?.addEventListener('click', () => {
      clearFeedback(container);
    });

    if (autoHideMs > 0) {
      container.dataset.timeoutId = String(
        window.setTimeout(() => {
          clearFeedback(container);
        }, autoHideMs)
      );
    }
  }

  function maybeScrollIntoView(element) {
    if (!element) {
      return;
    }

    const rect = element.getBoundingClientRect();
    const isVisible = rect.top >= 0 && rect.bottom <= window.innerHeight;

    if (!isVisible) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  function renderActionFeedback(type, message, autoHideMs = 0) {
    renderFeedback(actionFeedback, type, message, autoHideMs);
    maybeScrollIntoView(actionFeedback);
  }

  function setSearchBusy(isBusy) {
    searchInProgress = isBusy;
    searchBtn.disabled = isBusy;
    dniInput.readOnly = isBusy;

    if (isBusy) {
      if (!searchBtn.dataset.defaultLabel) {
        searchBtn.dataset.defaultLabel = searchBtn.textContent?.trim() || 'Buscar';
      }

      searchBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Buscando...
      `;
      return;
    }

    searchBtn.innerHTML = searchBtn.dataset.defaultLabel || 'Buscar';
  }

  function setEntryFormBusy(form, isBusy, loadingLabel) {
    const controls = form.querySelectorAll('button, textarea, input[type="file"]');
    controls.forEach((control) => {
      control.disabled = isBusy;
    });

    const submitBtn = form.querySelector('[data-submit-entry="1"]');
    if (!submitBtn) {
      return;
    }

    if (!submitBtn.dataset.defaultLabel) {
      submitBtn.dataset.defaultLabel = submitBtn.textContent?.trim() || 'Guardar';
    }

    if (isBusy) {
      submitBtn.innerHTML = `
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        ${escapeHtml(loadingLabel)}
      `;
      return;
    }

    submitBtn.innerHTML = submitBtn.dataset.defaultLabel;
  }

  function renderInlineEntrySuccess(form, message, autoHideMs = 2500) {
    if (!form) {
      return;
    }

    const previous = form.querySelector('.entry-inline-feedback');
    previous?.remove();

    const anchor =
      form.querySelector('.mb-3')
      || form;

    anchor.classList.add('position-relative');

    const bubble = document.createElement('div');
    bubble.className = 'entry-inline-feedback alert alert-success py-1 px-2 mb-0';
    bubble.setAttribute('role', 'status');
    bubble.textContent = message;
    anchor.appendChild(bubble);

    requestAnimationFrame(() => {
      bubble.classList.add('is-visible');
    });

    const hideAfterMs = Math.max(500, autoHideMs);
    const fadeOutMs = 220;
    window.setTimeout(() => {
      bubble.classList.remove('is-visible');
      window.setTimeout(() => {
        bubble.remove();
      }, fadeOutMs);
    }, hideAfterMs);
  }

  function validateSearchInput() {
    const normalized = normalizeDni(dniInput.value);
    dniInput.value = normalized;

    if (!isValidDni(normalized)) {
      dniInput.classList.add('is-invalid');
      return null;
    }

    dniInput.classList.remove('is-invalid');
    return normalized;
  }

  async function addEntry(form) {
    const formData = new FormData(form);
    const dni = normalizeDni(formData.get('dni'));
    const description = String(formData.get('description') ?? '').trim();
    const photoInput = form.querySelector('input[data-photo-input="1"]');
    const selectedPhoto = photoInput?.files?.[0];

    if (!isValidDni(dni)) {
      renderActionFeedback('warning', 'El DNI no es válido.');
      return;
    }

    if (selectedPhoto) {
      await uploadPhoto(dni, selectedPhoto, description, form);
      return;
    }

    if (description === '') {
      renderActionFeedback('warning', 'Completá la descripción o cargá una imagen.');
      return;
    }

    formData.set('dni', dni);
    setEntryFormBusy(form, true, 'Guardando...');

    try {
      const response = await fetch('/add', {
        method: 'POST',
        body: formData
      });

      if (response.status === 401) {
        window.location.href = buildSignInUrlWithReturnTo(dni);
        return;
      }

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.message || 'Error desconocido');
      }

      await searchByDni(dni, { showSuccessFeedback: false });
      const refreshedForm = document.getElementById('personForm');
      renderInlineEntrySuccess(refreshedForm, 'Entrada agregada correctamente.');
    } catch (error) {
      renderActionFeedback('danger', `Error al crear entrada: ${error.message}`);
      console.error(error);
    } finally {
      setEntryFormBusy(form, false, 'Guardando...');
    }
  }

  async function uploadPhoto(dni, photo, description, form) {
    if (!(photo instanceof File) || photo.size === 0) {
      renderActionFeedback('warning', 'Seleccioná una imagen.');
      return;
    }

    const formData = new FormData();
    formData.append('dni', dni);
    formData.append('photo', photo);
    formData.append('description', description);

    setEntryFormBusy(form, true, 'Subiendo...');

    try {
      const response = await fetch('/person/photo', {
        method: 'POST',
        body: formData
      });

      if (response.status === 401) {
        window.location.href = buildSignInUrlWithReturnTo(dni);
        return;
      }

      const data = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(data.message || 'Error desconocido');
      }

      await searchByDni(dni, { showSuccessFeedback: false });
      const refreshedForm = document.getElementById('personForm');
      renderInlineEntrySuccess(refreshedForm, 'Foto guardada correctamente.');
    } catch (error) {
      renderActionFeedback('danger', `Error al subir foto: ${error.message}`);
      console.error(error);
    } finally {
      setEntryFormBusy(form, false, 'Subiendo...');
    }
  }

  function bindPhotoPicker(buttonId, inputId, previewId) {
    const photoBtn = document.getElementById(buttonId);
    const photoInput = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    if (!photoBtn || !photoInput || !preview) {
      return;
    }

    const resetPreview = () => {
      const previousUrl = preview.dataset.objectUrl;
      if (previousUrl) {
        URL.revokeObjectURL(previousUrl);
        delete preview.dataset.objectUrl;
      }

      preview.innerHTML = '';
      preview.classList.add('d-none');
    };

    photoBtn.addEventListener('click', () => {
      photoInput.click();
    });

    photoInput.addEventListener('change', () => {
      const selectedPhoto = photoInput.files?.[0];

      if (!selectedPhoto) {
        resetPreview();
        return;
      }

      resetPreview();
      const objectUrl = URL.createObjectURL(selectedPhoto);
      preview.dataset.objectUrl = objectUrl;
      preview.classList.remove('d-none');
      preview.innerHTML = `
        <p class="small text-muted mb-2">Imagen cargada: ${escapeHtml(selectedPhoto.name)}</p>
        <img
          src="${objectUrl}"
          alt="Previsualización de imagen cargada"
          class="img-fluid rounded border"
          style="max-height: 220px; object-fit: contain;"
        >
      `;
    });
  }

  function bindEntryFormHandlers(photoButtonId, photoInputId, previewId) {
    const form = document.getElementById('personForm');
    if (!form) {
      return;
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      addEntry(form);
    });

    bindPhotoPicker(photoButtonId, photoInputId, previewId);

    const closeBtn = document.getElementById('closeBtn');
    closeBtn?.addEventListener('click', () => {
      modal.innerHTML = '';
      clearFeedback(actionFeedback);
    });
  }

  function renderGuestCtaCard(dni, title, body) {
    setMobileAddEntryVisible(false);
    modal.classList.add('prontuario-shell');
    modal.innerHTML = `
      <div class="card shadow-sm app-surface-card">
        <div class="card-body">
          <h3 class="card-title mb-3">${escapeHtml(title)}</h3>
          <p class="text-muted mb-3">${escapeHtml(body)}</p>
          <div class="alert alert-info py-2 mb-3">
            Para agregar entradas, primero tenés que iniciar sesión.
          </div>
          <div class="d-grid mb-2">
            <a href="${escapeHtml(buildSignInUrlWithReturnTo(dni))}" class="btn btn-primary">Iniciar sesión para agregar entrada</a>
          </div>
          <p class="small text-muted mb-0">
            ¿No tenés cuenta?
            <a href="${escapeHtml(buildRegisterUrlWithReturnTo(dni))}">Creala acá</a>.
          </p>
        </div>
      </div>
    `;
  }

  function renderGuestAddEntryCta(dni) {
    return `
      <h4 class="card-title mb-3">Agregar entrada</h4>
      <p class="text-muted mb-3">Para cargar una nueva entrada en este prontuario, iniciá sesión.</p>
      <div class="d-grid mb-2">
        <a href="${escapeHtml(buildSignInUrlWithReturnTo(dni))}" class="btn btn-primary">Iniciar sesión para agregar entrada</a>
      </div>
      <p class="small text-muted mb-0">
        ¿No tenés cuenta?
        <a href="${escapeHtml(buildRegisterUrlWithReturnTo(dni))}">Creala acá</a>.
      </p>
    `;
  }

  function renderNotFound(dni) {
    showSearchShortcut();
    if (!isAuthenticated) {
      renderGuestCtaCard(
        dni,
        `Prontuario DNI ${dni}`,
        'No hay entradas todavía para este DNI.'
      );
      return;
    }

    setMobileAddEntryVisible(true);

    modal.innerHTML = `
      <div class="card shadow-sm app-surface-card">
        <div class="card-body">
          <h3 class="card-title mb-3">Prontuario DNI ${dni}</h3>
          <p class="text-muted mb-4">No hay entradas. Creá la primera o agregá una foto:</p>

          <form id="personForm" novalidate>
            <div class="mb-3">
              <label for="entryDniNotFound" class="form-label">DNI</label>
              <div class="input-group">
                <span class="input-group-text">DNI</span>
                <input type="text" id="entryDniNotFound" name="dni" value="${dni}" class="form-control" readonly>
              </div>
            </div>

            <div class="mb-3">
              <label for="personDescription" class="form-label">Prontuario</label>
              <textarea name="description" id="personDescription" rows="4" class="form-control" placeholder="Describí la entrada..."></textarea>
            </div>

            <div class="d-flex gap-2">
              <button type="submit" id="addEntryBtn" class="btn btn-primary" data-submit-entry="1">Agregar Entrada</button>
              <button type="button" id="togglePhotoBtn" class="btn btn-outline-primary">Agregar foto del prontuario</button>
              <button type="button" id="closeBtn" class="btn btn-outline-secondary">Cancelar</button>
            </div>
            <input
              type="file"
              id="prontuarioPhotoInputNotFound"
              class="d-none"
              data-photo-input="1"
              accept="image/jpeg,image/png,image/webp"
            >
            <div id="photoPreviewNotFound" class="mt-3 d-none"></div>
          </form>
        </div>
      </div>
    `;

    bindEntryFormHandlers('togglePhotoBtn', 'prontuarioPhotoInputNotFound', 'photoPreviewNotFound');
  }

  function renderEntries(dni, entries) {
    showSearchShortcut();
    modal.classList.add('prontuario-shell');
    setMobileAddEntryVisible(isAuthenticated);
    const totalEntries = Array.isArray(entries) ? entries.length : 0;

    const entriesHtml = entries.map((entry) => {
      if (entry.type === 'photo') {
        return `
          <div class="card shadow-sm mb-2 app-surface-card app-entry-card app-entry-card--photo">
            <div class="card-body">
              <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
                <div class="d-flex flex-column gap-1">
                  <b class="entry-date">${entry.createdAt ?? ''}</b>
                  <span class="text-muted small">Usuario: ${escapeHtml(entry.ownerEmail ?? 'Usuario desconocido')}</span>
                </div>
                <span class="entry-kind-badge entry-kind-badge--photo">Foto</span>
              </div>
              <div class="mb-2 fw-semibold">Foto del prontuario</div>
              ${
                entry.photoUrl
                  ? `
                <a href="${escapeHtml(entry.photoUrl)}" target="_blank" rel="noopener noreferrer">
                  <img
                    src="${escapeHtml(entry.photoUrl)}"
                    alt="Foto del prontuario ${dni}"
                    class="img-fluid rounded border"
                    style="max-height: 320px; cursor: zoom-in;"
                  >
                </a>
              `
                  : '<div class="text-muted">Sin foto disponible</div>'
              }
              <div class="mt-2 entry-description">${escapeHtml(entry.description ?? '')}</div>
            </div>
          </div>
        `;
      }

      return `
        <div class="card shadow-sm mb-2 app-surface-card app-entry-card app-entry-card--text">
          <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-2">
              <div class="d-flex flex-column gap-1">
                <b class="entry-date">${entry.createdAt ?? ''}</b>
                <span class="text-muted small">Usuario: ${escapeHtml(entry.ownerEmail ?? 'Usuario desconocido')}</span>
              </div>
              <span class="entry-kind-badge entry-kind-badge--text">Entrada</span>
            </div>
            <div class="entry-description">${escapeHtml(entry.description ?? '')}</div>
          </div>
        </div>
      `;
    }).join('');

    modal.innerHTML = `
      <div class="prontuario-header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
          <div>
            <h3 class="mb-1">Prontuario DNI ${dni}</h3>
            <p class="prontuario-subtitle mb-0">Historial de entradas</p>
          </div>
          <span class="prontuario-count-badge">${totalEntries} ${totalEntries === 1 ? 'entrada' : 'entradas'}</span>
        </div>
      </div>

      <div id="entriesList">
        ${
          entriesHtml
            || '<div class="card shadow-sm app-surface-card app-entry-empty"><div class="card-body text-muted">Sin entradas.</div></div>'
        }
      </div>

      <div class="card shadow-sm mt-3 app-surface-card prontuario-editor-card">
        <div class="card-body">
          ${
            isAuthenticated
              ? `
            <h4 class="card-title mb-3">Nueva entrada</h4>
            <form id="personForm" novalidate>
              <input type="hidden" name="dni" value="${dni}">

              <div class="mb-3">
                <textarea name="description" id="personDescription" rows="4" class="form-control" placeholder="Describí la nueva entrada..."></textarea>
              </div>

              <div class="d-flex gap-2">
                <button type="submit" id="addEntryBtn" class="btn btn-primary" data-submit-entry="1">Agregar Entrada</button>
                <button type="button" id="togglePhotoBtn" class="btn btn-outline-primary">Agregar foto del prontuario</button>
                <button type="button" id="closeBtn" class="btn btn-outline-secondary">Cancelar</button>
              </div>
              <input
                type="file"
                id="prontuarioPhotoInputFound"
                class="d-none"
                data-photo-input="1"
                accept="image/jpeg,image/png,image/webp"
              >
              <div id="photoPreviewFound" class="mt-3 d-none"></div>
            </form>
          `
              : renderGuestAddEntryCta(dni)
          }
        </div>
      </div>
    `;

    if (isAuthenticated) {
      bindEntryFormHandlers('togglePhotoBtn', 'prontuarioPhotoInputFound', 'photoPreviewFound');
    }
  }

  async function searchByDni(dni, options = {}) {
    const { showSuccessFeedback = false } = options;
    const normalizedDni = normalizeDni(dni);
    if (!isValidDni(normalizedDni) || searchInProgress) {
      return;
    }

    clearFeedback(searchFeedback);
    setSearchBusy(true);

    try {
      const response = await fetch('/person?dni=' + encodeURIComponent(normalizedDni));
      const data = await response.json().catch(() => ({}));

      if (data.status === '404' || response.status === 404) {
        renderNotFound(normalizedDni);
        renderActionFeedback('info', `No hay entradas para el DNI ${normalizedDni}. Podés crear la primera.`);
        return;
      }

      if (response.ok && data.status === 'ok') {
        renderEntries(data.dni ?? normalizedDni, data.entries ?? []);
        if (showSuccessFeedback) {
          renderActionFeedback('info', `Prontuario DNI ${data.dni ?? normalizedDni} cargado.`, 2500);
        }
        return;
      }

      throw new Error(data.message || 'Respuesta inesperada del servidor');
    } catch (error) {
      renderActionFeedback('danger', `Error buscando prontuario: ${error.message}`);
      console.error(error);
    } finally {
      setSearchBusy(false);
    }
  }

  searchForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const validDni = validateSearchInput();
    if (!validDni) {
      renderFeedback(searchFeedback, 'warning', 'Ingresá un DNI válido de 7 u 8 dígitos.');
      dniInput.focus();
      return;
    }

    clearFeedback(actionFeedback);
    searchByDni(validDni, { showSuccessFeedback: true });
  });

  dniInput.addEventListener('input', () => {
    const normalized = normalizeDni(dniInput.value);
    if (dniInput.value !== normalized) {
      dniInput.value = normalized;
    }

    if (dniInput.classList.contains('is-invalid') && isValidDni(normalized)) {
      dniInput.classList.remove('is-invalid');
      clearFeedback(searchFeedback);
    }
  });

  const prefilledDni = new URLSearchParams(window.location.search).get('dni');
  if (prefilledDni) {
    dniInput.value = normalizeDni(prefilledDni);

    if (isValidDni(dniInput.value)) {
      searchByDni(dniInput.value, { showSuccessFeedback: false });
    } else {
      showSearchCard();
      dniInput.classList.add('is-invalid');
      renderFeedback(searchFeedback, 'warning', 'El DNI de la URL no es válido.');
    }
  }
});
