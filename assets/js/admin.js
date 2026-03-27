(function($){
  function nextId(prefix){ return prefix + Date.now().toString(36) + Math.floor(Math.random()*1000).toString(36); }
  function refreshChoiceVisibility(scope){
    $(scope).find('[data-step-card]').each(function(){
      var type = $(this).find('.gfl-step-type').val();
      $(this).find('[data-step-options]').toggleClass('is-hidden', type !== 'choice');
      $(this).find('.gfl-step-placeholder-field, .gfl-step-save-field, .gfl-step-save-label-field').toggleClass('is-hidden', type === 'complete');
    });
  }
  function optionTemplate(base){
    return $('#tmpl-gfl-option-row').html().replace(/__BASE__/g, base).replace(/__OPTION_INDEX__/g, nextId('opt_'));
  }
  $(document).on('click', '.gfl-add-step', function(){
    var html = $('#tmpl-gfl-step-card').html();
    var index = nextId('step_');
    html = html.replace(/__STEP_INDEX__/g, index).replace(/__STEP_ID__/g, nextId('step_'));
    $('.gfl-steps-builder').append(html);
    refreshChoiceVisibility(document);
  });
  $(document).on('click', '.gfl-delete-step', function(){
    if($('.gfl-steps-builder [data-step-card]').length <= 1){ return; }
    $(this).closest('[data-step-card]').remove();
  });
  $(document).on('click', '.gfl-move-step-up', function(){
    var card = $(this).closest('[data-step-card]');
    card.prev('[data-step-card]').before(card);
  });
  $(document).on('click', '.gfl-move-step-down', function(){
    var card = $(this).closest('[data-step-card]');
    card.next('[data-step-card]').after(card);
  });
  $(document).on('change', '.gfl-step-type', function(){ refreshChoiceVisibility(document); });
  $(document).on('click', '.gfl-add-option', function(){
    var step = $(this).closest('[data-step-card]');
    var base = step.find('.gfl-step-id').attr('name').replace(/\[id\]$/, '');
    step.find('[data-options-list]').append(optionTemplate(base));
  });
  $(document).on('click', '.gfl-delete-option', function(){
    var wrap = $(this).closest('[data-options-list]');
    if(wrap.find('[data-option-row]').length <= 1){
      $(this).closest('[data-option-row]').find('input').val('');
      return;
    }
    $(this).closest('[data-option-row]').remove();
  });
  refreshChoiceVisibility(document);
})(jQuery);