<?php if ($record->is_active && $this->user->hasPermission('allway.chat.accounts.send_test')): ?>
    <button type="button"
            data-control="popup"
            data-handler="onLoadSendTest"
            data-request-data="account_id: <?= $record->id ?>"
            href="javascript:;"
            data-bs-toggle="tooltip"
            data-bs-placement="bottom"
            data-bs-title="Enviar mensagem de teste"
            class="btn btn-sm btn-warning"><i class="fa-regular fa-paper-plane"></i></button>
<?php endif; ?>
<button type="button"
        data-request="onTestConnection"
        data-request-data="account_id: <?= $record->id ?>"
        href="javascript:;"
        data-bs-toggle="tooltip"
        data-bs-placement="bottom"
        data-bs-title="Testar conexão"
        class="btn btn-sm btn-secondary">
    <i class="fa-solid fa-network-wired"></i>
</button>
