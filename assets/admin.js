import './bootstrap.js';
import 'bootstrap';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/admin.scss';

// Import des composants média et éditeur
import './js/components/media-picker.js';
import './js/components/custom-editor.js';
import './js/components/media-selector.js';

// Initialisation automatique des composants
$(document).ready(function() {
    // Initialiser les éditeurs personnalisés
    $('textarea.custom-editor').each(function() {
        const $textarea = $(this);
        const enableMedia = $textarea.data('enable-media') === true;
        const height = $textarea.data('editor-height') || 300;
        
        $textarea.customEditor({
            height: height,
            enableMedia: enableMedia,
            placeholder: $textarea.attr('placeholder') || 'Tapez votre contenu ici...'
        });
    });
});

