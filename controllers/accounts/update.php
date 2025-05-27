<?php Block::put('body') ?>
<?php if (!$this->fatalError): ?>

<?= Form::open(['class' => 'layout']) ?>

<div class="layout-row">
    <?= $this->formRender() ?>
</div>

<?= Form::close() ?>

<?php else: ?>
    <p class="flash-message static error"><?= e(trans($this->fatalError)) ?></p>
    <p><a href="<?= Backend::url('allway/chat/accounts') ?>"
          class="btn btn-default"><?= e(trans('backend::lang.form.return_to_list')) ?></a></p>
<?php endif ?>

<?php Block::endPut() ?>

<?php Block::put('toolbar_actions') ?>
<div class="form-buttons">
    <div class="loading-indicator-container">
        <button
            type="submit"
            data-request="onSave"
            data-request-data="redirect:0"
            data-hotkey="ctrl+s, cmd+s"
            data-load-indicator="<?= e(trans('backend::lang.form.saving_name', ['name' => $formRecordName])) ?>"
            class="btn btn-primary">
            <?= e(trans('backend::lang.form.save')) ?>
        </button>
        <button
            type="button"
            data-request="onSave"
            data-request-data="close:1"
            data-hotkey="ctrl+enter, cmd+enter"
            data-load-indicator="<?= e(trans('backend::lang.form.saving_name', ['name' => $formRecordName])) ?>"
            class="btn btn-default">
            <?= e(trans('backend::lang.form.save_and_close')) ?>
        </button>
        <?php if (BackendAuth::userHasAccess('allway.chat.accounts.delete')): ?>
            <button
                type="button"
                class="btn btn-danger"
                data-request="onDelete"
                data-load-indicator="<?= e(trans('backend::lang.form.deleting_name', ['name' => $formRecordName])) ?>"
                data-request-confirm="<?= e(trans('backend::lang.form.confirm_delete')) ?>">
                <i class="fa-regular fa-trash-can"></i>
            </button>
        <?php endif ?>
        <span class="btn-text">
            <?= e(trans('backend::lang.form.or')) ?>
            <a href="<?= Backend::url('allway/chat/accounts') ?>"><?= e(trans('backend::lang.form.close')) ?></a>
        </span>
    </div>
</div>
<?php Block::endPut() ?>
