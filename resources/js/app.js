//

// data-confirm を持つフォームは、送信前に確認ダイアログを出す（キャンセルされたら送信しない）
document.addEventListener('submit', (event) => {
  const message = event.target instanceof HTMLFormElement ? event.target.dataset.confirm : undefined;
  if (message && !window.confirm(message)) {
    event.preventDefault();
  }
});
