(function ($, Drupal) {
  Drupal.behaviors.awakePriceMask = {
    attach: function (context, settings) {
      // Aplica a máscara no campo de preço 01 e 02 quando o documento estiver pronto.
      $('#edit-field-preco-1, #edit-field-preco-2', context).once('awakePriceMask').on('input', function () {
        let value = this.value.replace(/\D/g, ''); // Remove tudo que não for número
        value = (value / 100).toFixed(2) + ''; // Converte para decimal com duas casas
        value = value.replace('.', ','); // Substitui o ponto pela vírgula
        value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.'); // Insere o ponto separador de milhar
        this.value = value; // Atualiza o valor no campo
      });
    }
  };
})(jQuery, Drupal);
