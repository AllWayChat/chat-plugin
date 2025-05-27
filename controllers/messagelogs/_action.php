<button type="button"
        data-request="onRetryMessage"
        data-request-data="message_log_id: <?= $record->id ?>"
        href="javascript:;"
        data-bs-toggle="tooltip"
        data-bs-placement="bottom"
        data-bs-title="Enviar novamente"
        data-request-confirm="Tem certeza que deseja enviar novamente?"
        class="btn btn-sm btn-warning">
    <i class="fa-solid fa-rotate"></i>
</button>
