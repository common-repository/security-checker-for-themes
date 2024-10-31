const tabs = document.querySelectorAll('.navtab');
const contents = document.querySelectorAll('.content');
const underline = document.querySelector('.underline');

function updateUnderline() {
  const activeTab = document.querySelector('.navtab.active');
  underline.style.width = `${activeTab.offsetWidth}px`;
  underline.style.left = `${activeTab.offsetLeft}px`;
}

tabs.forEach(tab => {
  tab.addEventListener('click', () => {
    tabs.forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const target = tab.getAttribute('data-target');
    contents.forEach(content => {
      if (content.id === target) {
        content.classList.add('active');
      } else {
        content.classList.remove('active');
      }
    });
    updateUnderline();
  });
});

window.addEventListener('resize', updateUnderline);
updateUnderline();

document.addEventListener("DOMContentLoaded", function(event) {
    var $listChildPanels = jQuery('.content ul');  
    var hideListChildPanels = $listChildPanels.hide();
    
    jQuery('.content h3').click(function(e) {
        hideListChildPanels.slideUp();
        jQuery('.content h3').removeClass('shown').find('.arrows').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');

        var subList = jQuery(this).siblings();
        if(!jQuery(subList).is(':visible')){
            jQuery(subList).slideDown();
            jQuery(this).addClass('shown').find('.arrows').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        }

        e.preventDefault();
    });
});
