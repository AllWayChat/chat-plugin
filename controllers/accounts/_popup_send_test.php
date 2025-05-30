<div class="modal-header">
    <button type="button" class="close" data-dismiss="popup">&times;</button>
    <h4 class="modal-title">
        Enviar mensagem de teste
    </h4>
</div>
<form class="form-elements" role="form" data-request="onSendTest" data-popup-load-indicator>
    <div class="modal-body">
        <?= $formWidget->render() ?>
    </div>
    <div class="modal-footer">
        <button
            type="submit"
            class="btn btn-primary oc-icon-send"
            data-load-indicator="Enviando">
            Enviar
        </button>
        <button
            type="button"
            class="btn btn-default"
            data-dismiss="popup">
            <?= e(trans('backend::lang.relation.close')) ?>
        </button>
    </div>
</form>
