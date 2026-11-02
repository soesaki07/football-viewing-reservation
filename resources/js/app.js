//

// data-confirm を持つフォームは、送信前に確認ダイアログを出す（キャンセルされたら送信しない）。
// data-confirm-field と data-confirm-value があるときは、その入力欄がその値のときだけ出す
document.addEventListener('submit', (event) => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  const message = form.dataset.confirm;
  if (!message) {
    return;
  }
  const field = form.dataset.confirmField;
  if (field && form.elements.namedItem(field)?.value !== form.dataset.confirmValue) {
    return;
  }
  if (!window.confirm(message)) {
    event.preventDefault();
  }
});
