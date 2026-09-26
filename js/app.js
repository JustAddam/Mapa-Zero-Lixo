/**
 * Título: app.js — interações do navegador (mapazerolixo)
 * Autoria: ADDAM S. C
 *
 * Menu móvel, mapa Leaflet, geocodificação, upload de imagem e editor de texto.
 */
(function () {
  // Abre e fecha o menu em telas pequenas.
  const nav = document.getElementById("main-nav");
  const trigger = document.getElementById("menu-trigger");
  if (nav && trigger) {
    trigger.addEventListener("click", () => nav.classList.toggle("is-open"));
  }

  // Seleção de nota (estrelas) no formulário de avaliação.
  document.querySelectorAll("[data-stars]").forEach((row) => {
    const input = row.parentElement.querySelector('input[name="rating"]');
    row.querySelectorAll(".star-button").forEach((btn) => {
      btn.addEventListener("click", () => {
        const value = Number(btn.dataset.value);
        if (input) input.value = String(value);
        row.querySelectorAll(".star-button").forEach((el) => {
          el.classList.toggle("selected", Number(el.dataset.value) <= value);
        });
      });
    });
  });

  // Mapa público: marca pontos de coleta e abre o detalhe ao clicar.
  const mapEl = document.getElementById("map");
  if (mapEl && window.L) {
    const points = JSON.parse(mapEl.dataset.points || "[]");
    const selected = Number(mapEl.dataset.selected || 0);
    const map = L.map(mapEl).setView([-0.035, -51.066], 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      attribution: "&copy; OpenStreetMap",
      maxZoom: 19,
    }).addTo(map);
    points.forEach((point) => {
      const marker = L.marker([point.lat, point.lng]).addTo(map).bindPopup(point.name);
      marker.on("click", () => {
        const url = new URL(window.location.href);
        url.searchParams.set("ponto", String(point.id));
        window.location.href = url.toString();
      });
      if (point.id === selected) {
        map.setView([point.lat, point.lng], 15);
        marker.openPopup();
      }
    });
  }

  // Mapa do cadastro: o usuário arrasta o pino ou busca o endereço.
  const addressMap = document.getElementById("address-map");
  const latInput = document.getElementById("lat");
  const lngInput = document.getElementById("lng");
  if (addressMap && window.L) {
    const picker = L.map(addressMap).setView([-0.0354, -51.0664], 13);
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19 }).addTo(picker);
    let marker = L.marker([-0.0354, -51.0664], { draggable: true }).addTo(picker);
    const sync = (latlng) => {
      if (latInput) latInput.value = latlng.lat.toFixed(7);
      if (lngInput) lngInput.value = latlng.lng.toFixed(7);
    };
    marker.on("dragend", () => sync(marker.getLatLng()));
    picker.on("click", (ev) => {
      marker.setLatLng(ev.latlng);
      sync(ev.latlng);
    });
    // Botão "localizar": chama /api/geocode.php e move o pino.
    const geoBtn = document.getElementById("geocode-btn");
    const addressInput = document.getElementById("address-input");
    if (geoBtn && addressInput) {
      geoBtn.addEventListener("click", async () => {
        const q = addressInput.value.trim();
        if (q.length < 3) return;
        const res = await fetch("/api/geocode.php?q=" + encodeURIComponent(q));
        const data = await res.json();
        if (!res.ok) {
          alert(data.error || "Não encontrado");
          return;
        }
        const latlng = [data.lat, data.lng];
        picker.setView(latlng, 16);
        marker.setLatLng(latlng);
        sync({ lat: data.lat, lng: data.lng });
      });
    }
  }

  // Envia a imagem escolhida e grava a URL no campo oculto.
  document.querySelectorAll("input[type=file][data-upload]").forEach((input) => {
    input.addEventListener("change", async () => {
      const file = input.files && input.files[0];
      if (!file) return;
      const box = input.closest(".cover-upload-box") || input.parentElement;
      const trigger = box.querySelector(".cover-upload-trigger");
      const previous = trigger ? trigger.textContent : "";
      if (trigger) trigger.textContent = "Enviando...";
      const body = new FormData();
      body.append("file", file);
      try {
        const res = await fetch(input.dataset.upload, { method: "POST", body, credentials: "same-origin" });
        const data = await res.json();
        if (!res.ok) {
          alert(data.error || "Falha no upload");
          return;
        }
        const targetId = input.dataset.target || "imageUrl";
        const hidden = document.getElementById(targetId);
        if (hidden) hidden.value = data.url;
        let preview = box.querySelector(".image-upload-preview");
        if (!preview) {
          preview = document.createElement("img");
          preview.className = "image-upload-preview";
          preview.alt = "Prévia";
          box.appendChild(preview);
        }
        preview.src = data.url;
        preview.classList.remove("is-empty");
      } catch (err) {
        alert("Não foi possível enviar a imagem.");
      } finally {
        if (trigger) trigger.textContent = previous || "Escolher imagem";
      }
    });
  });

  // Atualiza a prévia da cor escolhida no painel de identidade visual.
  document.querySelectorAll(".color-input input[type=color]").forEach((input) => {
    const sync = () => {
      const wrap = input.closest(".color-input");
      if (!wrap) return;
      const code = wrap.querySelector("code");
      const swatch = wrap.querySelector(".color-swatch");
      if (code) code.textContent = input.value;
      if (swatch) swatch.style.background = input.value;
    };
    input.addEventListener("input", sync);
    sync();
  });

  // Barra de formatação: negrito, itálico, listas, alinhamento e link.
  const toolbarHtml = `
    <div class="rich-text-toolbar">
      <button type="button" data-cmd="bold" class="toolbar-text" title="Negrito">B</button>
      <button type="button" data-cmd="italic" title="Itálico"><em>I</em></button>
      <button type="button" data-cmd="underline" title="Sublinhado"><u>S</u></button>
      <span class="toolbar-divider"></span>
      <select data-font>
        <option value="">Fonte</option>
        <option value="Kumbh Sans">Kumbh Sans</option>
        <option value="Georgia">Georgia</option>
        <option value="Arial">Arial</option>
        <option value="Verdana">Verdana</option>
      </select>
      <select data-size>
        <option value="">Tamanho</option>
        <option value="2">Pequeno</option>
        <option value="3">Normal</option>
        <option value="4">Grande</option>
        <option value="5">Enorme</option>
      </select>
      <input type="color" data-color value="#173d32" title="Cor do texto" />
      <span class="toolbar-divider"></span>
      <button type="button" data-block="h2" class="toolbar-text" title="Título">H2</button>
      <button type="button" data-block="h3" class="toolbar-text" title="Subtítulo">H3</button>
      <button type="button" data-cmd="insertUnorderedList" title="Lista">•</button>
      <button type="button" data-cmd="insertOrderedList" title="Lista numerada">1.</button>
      <button type="button" data-block="blockquote" title="Citação">“</button>
      <button type="button" data-link title="Inserir link">🔗</button>
      <button type="button" data-cmd="justifyLeft" title="Alinhar à esquerda">⬅</button>
      <button type="button" data-cmd="justifyCenter" title="Centralizar">↔</button>
      <button type="button" data-cmd="removeFormat" title="Limpar formato">Tx</button>
    </div>
  `;

  // Troca o textarea por um editor visual e copia o HTML de volta no envio.
  function bindRichEditor(textarea) {
    if (textarea.dataset.richBound) return;
    textarea.dataset.richBound = "1";
    textarea.hidden = true;
    const editor = document.createElement("div");
    editor.className = "rich-text-editor";
    editor.innerHTML = toolbarHtml;
    const canvas = document.createElement("div");
    canvas.className = "rich-text-canvas";
    canvas.contentEditable = "true";
    canvas.dataset.placeholder = textarea.dataset.placeholder || "Escreva aqui...";
    canvas.innerHTML = textarea.value || "";
    editor.appendChild(canvas);
    textarea.after(editor);

    const sync = () => {
      textarea.value = canvas.innerHTML;
    };
    canvas.addEventListener("input", sync);
    canvas.addEventListener("blur", sync);
    if (textarea.form) textarea.form.addEventListener("submit", sync);

    // Aplica o comando de formatação na seleção atual do editor.
    const run = (cmd, value) => {
      canvas.focus();
      document.execCommand(cmd, false, value);
      sync();
    };

    editor.querySelectorAll("[data-cmd]").forEach((btn) => {
      btn.addEventListener("mousedown", (ev) => ev.preventDefault());
      btn.addEventListener("click", () => run(btn.dataset.cmd));
    });
    editor.querySelectorAll("[data-block]").forEach((btn) => {
      btn.addEventListener("mousedown", (ev) => ev.preventDefault());
      btn.addEventListener("click", () => run("formatBlock", "<" + btn.dataset.block + ">"));
    });
    const font = editor.querySelector("[data-font]");
    if (font) {
      font.addEventListener("change", () => {
        if (font.value) run("fontName", font.value);
        font.selectedIndex = 0;
      });
    }
    const size = editor.querySelector("[data-size]");
    if (size) {
      size.addEventListener("change", () => {
        if (size.value) run("fontSize", size.value);
        size.selectedIndex = 0;
      });
    }
    const color = editor.querySelector("[data-color]");
    if (color) {
      color.addEventListener("input", () => run("foreColor", color.value));
    }
    const link = editor.querySelector("[data-link]");
    if (link) {
      link.addEventListener("mousedown", (ev) => ev.preventDefault());
      link.addEventListener("click", () => {
        const url = window.prompt("Cole o endereço do link", "https://");
        if (url) run("createLink", url);
      });
    }
  }

  // Ativa o editor rico em todo textarea marcado com a classe js-rich.
  document.querySelectorAll("textarea.js-rich").forEach(bindRichEditor);
})();
