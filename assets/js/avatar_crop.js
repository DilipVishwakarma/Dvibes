(function () {
  function $(sel) {
    return document.querySelector(sel);
  }

  function dataUrlToBlob(dataUrl) {
    const parts = dataUrl.split(',');
    const mimeMatch = parts[0].match(/:(.*?);/);
    const mime = mimeMatch ? mimeMatch[1] : 'image/jpeg';
    const bstr = atob(parts[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) u8arr[n] = bstr.charCodeAt(n);
    return new Blob([u8arr], { type: mime });
  }

  document.addEventListener('DOMContentLoaded', function () {
    const input = $('#avatarInput');
    const preview = $('#avatarPreviewImg');
    const canvas = $('#avatarCropCanvas');
    const hiddenFile = $('#avatarCroppedFile');

    if (!input || !preview || !canvas || !hiddenFile) return;

    const ctx = canvas.getContext('2d');
    const state = {
      img: null,
      scale: 1,
      offsetX: 0,
      offsetY: 0,
      isDragging: false,
      dragStartX: 0,
      dragStartY: 0,
      panStartX: 0,
      panStartY: 0
    };

    // Crop viewport is a circle in the UI; we crop to a square region around it.
    // We'll render a 300x300 square crop from the centered square.
    const cropSize = 300;

    function fitToCanvas() {
      // We will show the image inside the canvas area with a default scale so it fills.
      const cw = canvas.width;
      const ch = canvas.height;
      if (!state.img) return;

      const iw = state.img.naturalWidth || state.img.width;
      const ih = state.img.naturalHeight || state.img.height;

      const scale = Math.max(cw / iw, ch / ih);
      state.scale = scale;
      const drawW = iw * scale;
      const drawH = ih * scale;
      state.offsetX = (cw - drawW) / 2;
      state.offsetY = (ch - drawH) / 2;
    }

    function draw() {
      const cw = canvas.width;
      const ch = canvas.height;
      ctx.clearRect(0, 0, cw, ch);

      if (state.img) {
        const iw = state.img.naturalWidth || state.img.width;
        const ih = state.img.naturalHeight || state.img.height;
        const drawW = iw * state.scale;
        const drawH = ih * state.scale;

        ctx.drawImage(state.img, state.offsetX, state.offsetY, drawW, drawH);
      }

      // Mask outside the circle viewport for UX
      const centerX = cw / 2;
      const centerY = ch / 2;
      const r = Math.min(cw, ch) / 2;

      ctx.save();
      ctx.beginPath();
      ctx.arc(centerX, centerY, r, 0, Math.PI * 2);
      ctx.clip();

      // Redraw a subtle border inside clip (optional)
      ctx.restore();

      ctx.save();
      ctx.globalCompositeOperation = 'source-over';
      ctx.fillStyle = 'rgba(0,0,0,0.35)';
      // Darken outside circle: draw full rect then cut circle using destination-out
      ctx.fillRect(0, 0, cw, ch);

      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      ctx.arc(centerX, centerY, r - 1, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();

      // Circle border
      ctx.save();
      ctx.strokeStyle = 'rgba(29,185,84,0.8)';
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.arc(centerX, centerY, r - 1, 0, Math.PI * 2);
      ctx.stroke();
      ctx.restore();
    }

    async function loadImageFromFile(file) {
      return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => {
          const img = new Image();
          img.onload = () => resolve(img);
          img.onerror = reject;
          img.src = reader.result;
        };
        reader.onerror = reject;
        reader.readAsDataURL(file);
      });
    }

    function updateHiddenCroppedFile() {
      if (!state.img) return;

      const cw = canvas.width;
      const ch = canvas.height;
      const iw = state.img.naturalWidth || state.img.width;
      const ih = state.img.naturalHeight || state.img.height;

      const centerX = cw / 2;
      const centerY = ch / 2;
      const r = Math.min(cw, ch) / 2;
      const squareSide = Math.floor(r * 2);

      // Region in canvas coords to sample (square that contains circle)
      const srcCanvasX = centerX - squareSide / 2;
      const srcCanvasY = centerY - squareSide / 2;

      // Convert to image coords
      // Canvas draw: img at (offsetX, offsetY) with size iw*scale, ih*scale
      const srcImgX = (srcCanvasX - state.offsetX) / state.scale;
      const srcImgY = (srcCanvasY - state.offsetY) / state.scale;
      const srcImgW = squareSide / state.scale;
      const srcImgH = squareSide / state.scale;

      const out = document.createElement('canvas');
      out.width = cropSize;
      out.height = cropSize;
      const octx = out.getContext('2d');

      // Draw cropped square to out canvas
      octx.clearRect(0, 0, cropSize, cropSize);
      octx.drawImage(state.img, srcImgX, srcImgY, srcImgW, srcImgH, 0, 0, cropSize, cropSize);

      // Apply circular mask to match how avatar will look
      octx.save();
      octx.beginPath();
      octx.arc(cropSize / 2, cropSize / 2, cropSize / 2 - 1, 0, Math.PI * 2);
      octx.closePath();
      octx.clip();
      octx.drawImage(out, 0, 0);
      octx.restore();

      // Convert to blob and store in hidden file input via DataTransfer
      const dataUrl = out.toDataURL('image/jpeg', 0.86);
      const blob = dataUrlToBlob(dataUrl);

      try {
        const dt = new DataTransfer();
        dt.items.add(new File([blob], 'avatar_cropped.jpg', { type: 'image/jpeg' }));
        hiddenFile.files = dt.files;
      } catch (e) {
        // Fallback: store data url in hidden field if file input assignment isn't supported
        hiddenFile.value = dataUrl;
      }

      // Update preview image
      preview.src = out.toDataURL('image/jpeg', 0.86);
    }

    input.addEventListener('change', async function () {
      const file = input.files && input.files[0];
      if (!file) return;

      state.img = null;
      preview.src = '';
      ctx.clearRect(0, 0, canvas.width, canvas.height);

      state.img = await loadImageFromFile(file);
      fitToCanvas();
      draw();
      updateHiddenCroppedFile();
    });

    canvas.addEventListener('mousedown', function (e) {
      if (!state.img) return;
      state.isDragging = true;
      state.dragStartX = e.clientX;
      state.dragStartY = e.clientY;
      state.panStartX = state.offsetX;
      state.panStartY = state.offsetY;
    });

    window.addEventListener('mousemove', function (e) {
      if (!state.isDragging) return;
      const dx = e.clientX - state.dragStartX;
      const dy = e.clientY - state.dragStartY;
      state.offsetX = state.panStartX + dx;
      state.offsetY = state.panStartY + dy;
      draw();
      updateHiddenCroppedFile();
    });

    window.addEventListener('mouseup', function () {
      state.isDragging = false;
    });

    // Zoom with wheel
    canvas.addEventListener('wheel', function (e) {
      if (!state.img) return;
      e.preventDefault();

      const factor = e.deltaY < 0 ? 1.08 : 1 / 1.08;

      // Zoom around center of canvas
      const cw = canvas.width;
      const ch = canvas.height;
      const cx = cw / 2;
      const cy = ch / 2;

      const prevScale = state.scale;
      const newScale = Math.max(0.2, Math.min(6, prevScale * factor));

      // Adjust offsets to keep center stable
      const scaleRatio = newScale / prevScale;
      state.offsetX = cx - (cx - state.offsetX) * scaleRatio;
      state.offsetY = cy - (cy - state.offsetY) * scaleRatio;
      state.scale = newScale;

      draw();
      updateHiddenCroppedFile();
    }, { passive: false });
  });
})();

