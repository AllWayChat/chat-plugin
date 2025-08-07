<?php if (isset($error)): ?>
    <div class="report-widget bg-danger">
        <div class="card-body d-flex justify-content-center align-items-center">
            <div class="text-center">
                <i class="fa oc-icon-exclamation-triangle text-white fs-3x mb-3"></i>
                <div class="text-white fw-bold"><?= e($error) ?></div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="report-widget report-widget-chatstats <?= $bgColor ?>">
        <h3 style="position: absolute; top: -9999px; left: -9999px;"><?= e($this->property('title')) ?></h3>
        <div class="card-body d-flex justify-content-between align-items-start flex-column h-100">
            <!-- Header com ícone e label -->
            <div class="d-flex justify-content-between align-items-start w-100 mb-3">
                <div class="d-flex flex-column">
                    <div class="chat-stats-label <?= $textColor ?>"><?= e($title) ?></div>
                    <div class="chat-stats-period <?= $descriptionColor ?>"><?= e($periodLabel) ?></div>
                </div>
                <div>
                    <i class="fa oc-icon-<?= $this->property('icon') ?> <?= $iconColor ?> fs-2x fa-beat-hover chat-stats-icon"></i>
                </div>
            </div>

            <!-- Valor principal - centralizado e destacado -->
            <div class="d-flex justify-content-center align-items-center flex-grow-1 w-100">
                <div class="text-center">
                    <div class="chat-stats-value <?= $textColor ?>"><?= number_format($statValue) ?></div>
                </div>
            </div>

            <!-- Footer com crescimento e título customizado -->
            <div class="w-100 mt-auto">
                <!-- Crescimento (se habilitado) -->
                <?php if ($this->property('show_growth') && isset($growth)): ?>
                    <div class="d-flex justify-content-center mb-3">
                        <div class="chat-stats-growth <?= $growth['direction'] ?>">
                            <?php if ($growth['direction'] === 'up'): ?>
                                <i class="fa fa-arrow-up me-1"></i>
                            <?php elseif ($growth['direction'] === 'down'): ?>
                                <i class="fa fa-arrow-down me-1"></i>
                            <?php else: ?>
                                <i class="fa fa-minus me-1"></i>
                            <?php endif; ?>
                            <span><?= e($growth['text']) ?></span>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Título customizado já exibido no topo -->
            </div>
        </div>
    </div>
<?php endif; ?>
