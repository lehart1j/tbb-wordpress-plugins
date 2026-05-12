(() => {
  const tbody = document.getElementById("tbb-cf-fields-body");
  const addBtn = document.getElementById("tbb-cf-add-field");
  if (!tbody || !addBtn) return;

  function bindRemove(row) {
    const btn = row.querySelector(".tbb-cf-remove-row");
    if (btn) {
      btn.addEventListener("click", () => {
        if (tbody.querySelectorAll("tr").length <= 1) return;
        row.remove();
      });
    }
  }

  tbody.querySelectorAll("tr").forEach(bindRemove);

  addBtn.addEventListener("click", () => {
    const first = tbody.querySelector("tr");
    if (!first) return;
    const row = first.cloneNode(true);
    row.querySelectorAll("input").forEach((inp) => {
      if (inp.type === "checkbox") inp.checked = false;
      else inp.value = "";
    });
    const sel = row.querySelector('select[name="field_type[]"]');
    if (sel) sel.value = "text";
    const req = row.querySelector('select[name="field_required[]"]');
    if (req) req.value = "0";
    tbody.appendChild(row);
    bindRemove(row);
  });
})();
