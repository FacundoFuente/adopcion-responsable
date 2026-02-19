import './styles/homepage.css';

document.addEventListener("DOMContentLoaded", function () {

  const dniInput = document.getElementById('dni');
  const modal = document.getElementById('personModal');
  const searchBtn = document.getElementById('searchButton');

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

function addEntry() {
  const form = document.getElementById('personForm');
  const formData = new FormData(form);
  const dni = String(formData.get('dni') ?? '').trim();

  fetch('/add', {
    method: 'POST',
    body: formData
  })
    .then(async (response) => {
        if (response.status === 401) {
        window.location.href = '/sign-in';
        return;
    }

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.message || "Error desconocido");
    }

    return data;
    })

    .then((data) => {
      if (!data) return;

      if (data.status === "ok") {
        alert("Entrada agregada correctamente");
        searchByDni(dni);
      } else {
        alert(data.message || "Error");
      }
    })
    .catch((error) => {
      alert("❌ Error al crear entrada: " + error.message);
      console.error(error);
    });
}

function uploadPhoto() {
  const form = document.getElementById('photoForm');
  const formData = new FormData(form);
  const dni = String(formData.get('dni') ?? '').trim();
  const photo = formData.get('photo');

  if (!(photo instanceof File) || photo.size === 0) {
    alert('Seleccioná una imagen');
    return;
  }

  fetch('/person/photo', {
    method: 'POST',
    body: formData
  })
    .then(async (response) => {
      if (response.status === 401) {
        window.location.href = '/sign-in';
        return;
      }

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Error desconocido');
      }

      return data;
    })
    .then((data) => {
      if (!data) return;
      alert('Foto guardada correctamente');
      searchByDni(dni);
    })
    .catch((error) => {
      alert('❌ Error al subir foto: ' + error.message);
      console.error(error);
    });
}

  function renderNotFound(dni) {
    modal.innerHTML = `
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h3 class="card-title mb-3">Prontuario DNI ${dni}</h3>
          <p class="text-muted mb-4">No hay entradas. Creá la primera:</p>

          <form id="personForm">
            <div class="mb-3">
              <label for="entryDniNotFound" class="form-label">DNI</label>
              <div class="input-group">
                <span class="input-group-text">DNI</span>
                <input type="number" id="entryDniNotFound" name="dni" value="${dni}" class="form-control" readonly>
              </div>
            </div>

            <div class="mb-3">
              <label for="personDescription" class="form-label">Prontuario</label>
              <textarea name="description" id="personDescription" rows="4" class="form-control" placeholder="Describí la entrada..."></textarea>
            </div>

            <div class="d-flex gap-2">
              <button type="button" id="addEntryBtn" class="btn btn-primary">Agregar Entrada</button>
              <button type="button" id="closeBtn" class="btn btn-outline-secondary">Cancelar</button>
            </div>
          </form>
        </div>
      </div>
    `;

    document.getElementById('addEntryBtn').addEventListener('click', addEntry);
    document.getElementById('closeBtn').addEventListener('click', () => {
      modal.innerHTML = '';
    });
  }

  function renderEntries(dni, entries) {
    const entriesHtml = entries.map(e => {
      if (e.type === 'photo') {
        return `
          <div class="card shadow-sm mb-2">
            <div class="card-body">
              <div class="d-flex flex-column flex-md-row justify-content-between gap-1 mb-2">
                <b>${e.createdAt ?? ''}</b>
                <span class="text-muted">Usuario: ${escapeHtml(e.ownerEmail ?? 'Usuario desconocido')}</span>
              </div>
              <div class="mb-2"><b>Foto del prontuario</b></div>
              ${e.photoUrl ? `
                <a href="${escapeHtml(e.photoUrl)}" target="_blank" rel="noopener noreferrer">
                  <img
                    src="${escapeHtml(e.photoUrl)}"
                    alt="Foto del prontuario ${dni}"
                    class="img-fluid rounded border"
                    style="max-height: 320px; cursor: zoom-in;"
                  >
                </a>
              ` : '<div class="text-muted">Sin foto disponible</div>'}
              <div class="mt-2">${escapeHtml(e.description ?? '')}</div>
            </div>
          </div>
        `;
      }

      return `
        <div class="card shadow-sm mb-2">
          <div class="card-body">
            <div class="d-flex flex-column flex-md-row justify-content-between gap-1 mb-2">
              <b>${e.createdAt ?? ''}</b>
              <span class="text-muted">Usuario: ${escapeHtml(e.ownerEmail ?? 'Usuario desconocido')}</span>
            </div>
            <div>${escapeHtml(e.description)}</div>
          </div>
        </div>
      `;
    }).join('');

    modal.innerHTML = `
      <h3 class="mb-3">Prontuario DNI ${dni}</h3>

      <div id="entriesList">
        ${entriesHtml || '<p>Sin entradas</p>'}
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-body p-4">
          <h4 class="card-title mb-3">Nueva entrada</h4>
          <form id="personForm">
            <div class="mb-3">
              <label for="entryDniFound" class="form-label">DNI</label>
              <div class="input-group">
                <span class="input-group-text">DNI</span>
                <input type="number" id="entryDniFound" name="dni" value="${dni}" class="form-control" readonly>
              </div>
            </div>

            <div class="mb-3">
              <label for="personDescription" class="form-label">Prontuario</label>
              <textarea name="description" id="personDescription" rows="4" class="form-control" placeholder="Describí la nueva entrada..."></textarea>
            </div>

            <div class="d-flex gap-2">
              <button type="button" id="addEntryBtn" class="btn btn-primary">Agregar Entrada</button>
              <button type="button" id="togglePhotoBtn" class="btn btn-outline-primary">Agregar foto del prontuario</button>
              <button type="button" id="closeBtn" class="btn btn-outline-secondary">Cancelar</button>
            </div>
          </form>
        </div>
      </div>

      <div id="photoUploadModal" class="card shadow-sm mt-3 d-none">
        <div class="card-body p-4">
          <h4 class="card-title mb-3">Foto del prontuario (opcional)</h4>
          <p class="text-muted mb-3">
            Seleccioná una imagen para adjuntarla como nueva entrada del prontuario.
          </p>

          <form id="photoForm">
            <input type="hidden" name="dni" value="${dni}">
            <div class="mb-3">
              <label for="prontuarioPhoto" class="form-label">Imagen (JPG, PNG o WEBP, máx 5MB)</label>
              <input type="file" id="prontuarioPhoto" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
            </div>
            <div class="d-flex gap-2">
              <button type="button" id="uploadPhotoBtn" class="btn btn-primary">Subir foto</button>
              <button type="button" id="hidePhotoBtn" class="btn btn-outline-secondary">Cerrar</button>
            </div>
          </form>
        </div>
      </div>
    `;

    const photoUploadModal = document.getElementById('photoUploadModal');
    document.getElementById('togglePhotoBtn').addEventListener('click', () => {
      photoUploadModal.classList.remove('d-none');
    });
    document.getElementById('hidePhotoBtn').addEventListener('click', () => {
      photoUploadModal.classList.add('d-none');
    });
    document.getElementById('uploadPhotoBtn').addEventListener('click', uploadPhoto);
    document.getElementById('addEntryBtn').addEventListener('click', addEntry);
    document.getElementById('closeBtn').addEventListener('click', () => {
      modal.innerHTML = '';
    });
  }

  function searchByDni(dni) {
    fetch('/person?dni=' + encodeURIComponent(dni))
      .then(async r => {
        const data = await r.json().catch(() => ({}));
        // si el backend manda 404 real, igualmente lo manejamos por status
        return data;
      })
      .then(data => {
        if (data.status === '404') {
          renderNotFound(dni);
          return;
        }

        if (data.status === 'ok') {
          // tu backend modificado debería devolver entries
          renderEntries(data.dni ?? dni, data.entries ?? []);
          return;
        }

        alert(data.message || "Respuesta inesperada del servidor");
      })
      .catch(err => {
        alert("❌ Error buscando prontuario");
        console.error(err);
      });
  }

  searchBtn.onclick = function () {
    const dni = dniInput.value.trim();

    if (!dni) {
      alert("Ingresá un DNI");
      return;
    }

    searchByDni(dni);
  };

});
