<div class="modal-header">
    <button type="button" class="close" data-dismiss="popup">&times;</button>
    <h4 class="modal-title">
        Enviar mensagem de teste
    </h4>
</div>
<form class="form-elements" role="form" data-request="onSendTest" data-popup-load-indicator>
    <div class="modal-body">
        <input type="hidden" name="account_id" value="<?= $account_id ?>">
        
        <div class="form-group dropdown-field span-full">
            <label>Canal</label>
            <select class="form-control custom-select" name="inbox_id" required>
                <option value="">Selecione um canal</option>
                <?php foreach ($inboxes as $inbox): ?>
                    <option value="<?= $inbox['id'] ?>">
                        <?= e($inbox['name']) ?> (<?= e($inbox['channel_type']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group span-full">
            <label>Nome do Contato</label>
            <input
                type="text"
                name="contact_name"
                class="form-control"
                placeholder="Digite o nome do contato"
                required>
        </div>
        
        <div class="form-group span-full">
            <label>Contato</label>
            <input
                type="text"
                name="contact"
                class="form-control"
                placeholder="Digite o email ou telefone do contato"
                required>
        </div>
        
        <div class="form-group dropdown-field span-full">
            <label>Tipo de Mensagem</label>
            <select class="form-control custom-select" name="message_type" id="message_type" onchange="toggleMessageFields()">
                <option value="text">Texto</option>
                <option value="image">Imagem</option>
                <option value="document">Arquivo</option>
            </select>
        </div>
        
        <div class="form-group span-full" id="text_field">
            <label>Mensagem</label>
            <textarea
                name="message"
                class="form-control"
                rows="5"
                placeholder="Digite a mensagem que deseja enviar"></textarea>
        </div>
        
        <div class="form-group span-full" id="image_field" style="display: none;">
            <label>URL da Imagem</label>
            <input
                type="url"
                name="image_url"
                class="form-control"
                placeholder="https://exemplo.com/imagem.jpg">
        </div>
        
        <div class="form-group span-full" id="document_field" style="display: none;">
            <label>URL do Arquivo</label>
            <input
                type="url"
                name="document_url"
                class="form-control"
                placeholder="https://exemplo.com/arquivo.pdf">
        </div>
        
        <div class="form-group span-full" id="filename_field" style="display: none;">
            <label>Nome do Arquivo (opcional)</label>
            <input
                type="text"
                name="filename"
                class="form-control"
                placeholder="documento.pdf">
        </div>
        
        <div class="form-group span-full" id="caption_field" style="display: none;">
            <label>Legenda (opcional)</label>
            <textarea
                name="caption"
                class="form-control"
                rows="3"
                placeholder="Digite uma legenda para a imagem ou arquivo"></textarea>
        </div>
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

<script>
function toggleMessageFields() {
    const messageType = document.getElementById('message_type').value;
    
    // Hide all fields first
    document.getElementById('text_field').style.display = 'none';
    document.getElementById('image_field').style.display = 'none';
    document.getElementById('document_field').style.display = 'none';
    document.getElementById('filename_field').style.display = 'none';
    document.getElementById('caption_field').style.display = 'none';
    
    // Show relevant fields based on type
    if (messageType === 'text') {
        document.getElementById('text_field').style.display = 'block';
    } else if (messageType === 'image') {
        document.getElementById('image_field').style.display = 'block';
        document.getElementById('caption_field').style.display = 'block';
    } else if (messageType === 'document') {
        document.getElementById('document_field').style.display = 'block';
        document.getElementById('filename_field').style.display = 'block';
        document.getElementById('caption_field').style.display = 'block';
    }
}
</script>
