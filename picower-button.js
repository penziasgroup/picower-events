(function() {

    // Speaker Name Button
    tinymce.PluginManager.add('picower_speaker_name_button', function( editor, url ) {
	if (editor.id == '_evcal_ec_f1a1_cus') {
            editor.addButton( 'picower_speaker_name_button', {
                text: 'Speaker Name',
                icon: false,
                onclick: function() {
		    var content = editor.selection.getContent();
                    content = '<span class="speaker_name">' + content + '</span>';
 		    editor.selection.setContent(content);
                }
            });
	}
    });

    // Speaker Image Button
    tinymce.PluginManager.add('picower_speaker_image_button', function( editor, url ) {
        if (editor.id == '_evcal_ec_f1a1_cus') {
            editor.addButton( 'picower_speaker_image_button', {
                text: 'Speaker Image',
                icon: false,
                onclick: function() {
                    var content = editor.selection.getContent();
                    content = '<span class="speaker_image">' + content + '</span>';
                    editor.selection.setContent(content);
                }
	    });
        }
    });

    // Speaker Bio Button
    tinymce.PluginManager.add('picower_speaker_bio_button', function( editor, url ) {
        if (editor.id == '_evcal_ec_f1a1_cus') {
            editor.addButton( 'picower_speaker_bio_button', {
                text: 'Speaker Bio',
                icon: false,
                onclick: function() {
                    var content = editor.selection.getContent();
                    content = '<span class="speaker_bio">' + content + '</span>';
                    editor.selection.setContent(content);
                }
	    });
        }
    });



})();
