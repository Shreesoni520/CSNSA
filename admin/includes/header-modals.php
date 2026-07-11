<div class="modal fade modal-shortcut modal-slide" tabindex="-1" role="dialog" aria-labelledby="shortcutModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="shortcutModalLabel">Atalhos</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body px-5">
        <div class="row align-items-center">
          <div class="col-6 text-center mb-4">
            <a href="<?php echo htmlspecialchars(admin_url('inicio')); ?>" class="text-decoration-none text-body">
              <div class="squircle bg-primary justify-content-center">
                <i class="fe fe-home fe-32 align-self-center text-white"></i>
              </div>
              <p class="mb-0 mt-2">Início</p>
            </a>
          </div>
          <div class="col-6 text-center mb-4">
            <a href="<?php echo htmlspecialchars(admin_url('funcionarios')); ?>" class="text-decoration-none text-body">
              <div class="squircle bg-primary justify-content-center">
                <i class="fe fe-users fe-32 align-self-center text-white"></i>
              </div>
              <p class="mb-0 mt-2">Funcionários</p>
            </a>
          </div>
        </div>
        <div class="row align-items-center">
          <div class="col-6 text-center mb-4">
            <a href="<?php echo htmlspecialchars(admin_url('ponto')); ?>" class="text-decoration-none text-body">
              <div class="squircle bg-primary justify-content-center">
                <i class="fe fe-clock fe-32 align-self-center text-white"></i>
              </div>
              <p class="mb-0 mt-2">Ponto</p>
            </a>
          </div>
          <div class="col-6 text-center mb-4">
            <a href="<?php echo htmlspecialchars(admin_url('escala_mensal')); ?>" class="text-decoration-none text-body">
              <div class="squircle bg-primary justify-content-center">
                <i class="fe fe-calendar fe-32 align-self-center text-white"></i>
              </div>
              <p class="mb-0 mt-2">Escala Mensal</p>
            </a>
          </div>
        </div>
        <div class="row align-items-center">
          <div class="col-6 text-center mb-4">
            <a href="<?php echo htmlspecialchars(admin_url('ausencias')); ?>" class="text-decoration-none text-body">
              <div class="squircle bg-primary justify-content-center">
                <i class="fe fe-minus-circle fe-32 align-self-center text-white"></i>
              </div>
              <p class="mb-0 mt-2">Ausências</p>
            </a>
          </div>
          <div class="col-6 text-center mb-4">
            <a href="<?php echo htmlspecialchars(admin_url('profile')); ?>" class="text-decoration-none text-body">
              <div class="squircle bg-success justify-content-center">
                <i class="fe fe-user fe-32 align-self-center text-white"></i>
              </div>
              <p class="mb-0 mt-2">Perfil</p>
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
