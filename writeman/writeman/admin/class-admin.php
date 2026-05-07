<?php
class WriteMan_Admin {
    private $tabs = ['inicio', 'fuentes', 'general', 'ia', 'publicacion', 'programacion', 'cola'];
    private $active_tab;
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'register_all_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_ajax_writeman_run_now', [$this, 'ajax_run_now']);
        add_action('wp_ajax_writeman_sync_feeds', [$this, 'ajax_sync_feeds']);
        add_action('wp_ajax_writeman_test_feed', [$this, 'ajax_test_feed']);
        add_action('wp_ajax_writeman_test_specific_feed', [$this, 'ajax_test_specific_feed']);
        add_action('wp_ajax_writeman_refresh_queue', [$this, 'ajax_refresh_queue']);
        add_action('wp_ajax_writeman_clear_queue', [$this, 'ajax_clear_queue']);
        add_action('wp_ajax_writeman_test_ai_connection', [$this, 'ajax_test_ai_connection']);
        add_action('wp_ajax_writeman_download_log', [$this, 'ajax_download_log']);
        add_action('admin_notices', [$this, 'show_admin_notices']);
    }
    
    public function add_menu() {
        add_menu_page('WriteMan', 'WriteMan', 'manage_options', 'writeman', [$this, 'render_page'], 'dashicons-media-text', 25);
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_writeman') return;
        wp_enqueue_style('writeman-admin', WRITEMAN_PLUGIN_URL . 'admin/css/admin.css', [], WRITEMAN_VERSION);
        wp_enqueue_script('writeman-admin', WRITEMAN_PLUGIN_URL . 'admin/js/admin.js', ['jquery'], WRITEMAN_VERSION, true);
        wp_localize_script('writeman-admin', 'writeman_ajax', ['ajax_url' => admin_url('admin-ajax.php'), 'nonce' => wp_create_nonce('writeman_nonce')]);
    }
    
    public function register_all_settings() {
        // Fuentes
        register_setting('writeman_fuentes', 'writeman_feeds', [$this, 'sanitize_feeds']);
        add_settings_section('wm_feeds', '', null, 'writeman_fuentes');
        add_settings_field('feeds_list', '', [$this, 'render_feeds_ui'], 'writeman_fuentes', 'wm_feeds');
        
        // General
        register_setting('writeman_general', 'writeman_quality_threshold', 'intval');
        register_setting('writeman_general', 'writeman_viral_threshold', 'intval');
        register_setting('writeman_general', 'writeman_writing_style', 'sanitize_text_field');
        register_setting('writeman_general', 'writeman_custom_prompt', 'wp_kses_post');
        add_settings_section('wm_general', 'Configuración de contenido', null, 'writeman_general');
        add_settings_field('quality', 'Umbral de calidad (0-100)', [$this, 'field_quality'], 'writeman_general', 'wm_general');
        add_settings_field('viral', 'Umbral de viralidad (0-100)', [$this, 'field_viral'], 'writeman_general', 'wm_general');
        add_settings_field('style', 'Estilo de redacción', [$this, 'field_style'], 'writeman_general', 'wm_general');
        add_settings_field('prompt', 'Prompt personalizado', [$this, 'field_prompt'], 'writeman_general', 'wm_general');
        
        // IA
        register_setting('writeman_ia', 'writeman_ai_provider', 'sanitize_text_field');
        register_setting('writeman_ia', 'writeman_ai_api_key', 'sanitize_text_field');
        register_setting('writeman_ia', 'writeman_ai_model', 'sanitize_text_field');
        register_setting('writeman_ia', 'writeman_ai_fallback_model', 'sanitize_text_field');
        add_settings_section('wm_ia', 'Configuración de Inteligencia Artificial', null, 'writeman_ia');
        add_settings_field('provider', 'Proveedor de IA', [$this, 'field_provider'], 'writeman_ia', 'wm_ia');
        add_settings_field('api_key', 'API Key', [$this, 'field_api_key'], 'writeman_ia', 'wm_ia');
        add_settings_field('model', 'Modelo principal', [$this, 'field_model'], 'writeman_ia', 'wm_ia');
        add_settings_field('fallback_model', 'Modelo de respaldo', [$this, 'field_fallback_model'], 'writeman_ia', 'wm_ia');
        add_settings_field('test', '', [$this, 'field_test'], 'writeman_ia', 'wm_ia');
        
        // Publicación
        register_setting('writeman_publicacion', 'writeman_max_posts_per_run', 'intval');
        register_setting('writeman_publicacion', 'writeman_post_status', 'sanitize_key');
        register_setting('writeman_publicacion', 'writeman_post_format', 'sanitize_key');
        register_setting('writeman_publicacion', 'writeman_allow_comments', 'intval');
        register_setting('writeman_publicacion', 'writeman_allow_pings', 'intval');
        add_settings_section('wm_publish', 'Opciones de publicación', null, 'writeman_publicacion');
        add_settings_field('max_posts', 'Máximo de artículos por ejecución', [$this, 'field_max_posts'], 'writeman_publicacion', 'wm_publish');
        add_settings_field('post_status', 'Estado del post', [$this, 'field_post_status'], 'writeman_publicacion', 'wm_publish');
        add_settings_field('post_format', 'Formato del post', [$this, 'field_post_format'], 'writeman_publicacion', 'wm_publish');
        add_settings_field('comments', 'Permitir comentarios', [$this, 'field_comments'], 'writeman_publicacion', 'wm_publish');
        add_settings_field('pings', 'Permitir trackbacks', [$this, 'field_pings'], 'writeman_publicacion', 'wm_publish');
        
        // Programación (con mensaje de éxito/error al guardar)
        register_setting('writeman_programacion', 'writeman_cron_schedule', [$this, 'sanitize_cron_schedule']);
        add_settings_section('wm_schedule', 'Programación de tareas', null, 'writeman_programacion');
        add_settings_field('cron_config', '', [$this, 'render_cron_ui'], 'writeman_programacion', 'wm_schedule');
        
        // Ya no hay Social
    }
    
    // Campos
    public function field_quality() { echo '<input type="number" name="writeman_quality_threshold" value="'.esc_attr(get_option('writeman_quality_threshold',30)).'"><p class="description">Mínimo para encolar un artículo (0-100).</p>'; }
    public function field_viral() { echo '<input type="number" name="writeman_viral_threshold" value="'.esc_attr(get_option('writeman_viral_threshold',30)).'"><p class="description">Mínimo de viralidad para generar.</p>'; }
    public function field_style() { echo '<input type="text" name="writeman_writing_style" value="'.esc_attr(get_option('writeman_writing_style','profesional, informativo')).'">'; }
    public function field_prompt() { echo '<textarea name="writeman_custom_prompt" rows="3" cols="60">'.esc_textarea(get_option('writeman_custom_prompt','')).'</textarea>'; }
    public function field_provider() { $p=get_option('writeman_ai_provider','groq'); echo '<select name="writeman_ai_provider"><option value="groq" '.selected($p,'groq',false).'>Groq (recomendado)</option><option value="openrouter" '.selected($p,'openrouter',false).'>OpenRouter</option><option value="huggingface" '.selected($p,'huggingface',false).'>Hugging Face</option></select>'; }
    public function field_api_key() { echo '<input type="password" name="writeman_ai_api_key" value="'.esc_attr(get_option('writeman_ai_api_key','')).'" class="regular-text"><p class="description">Obtén clave gratis en <a href="https://console.groq.com/keys" target="_blank">groq.com</a> o <a href="https://openrouter.ai/keys" target="_blank">openrouter.ai</a></p>'; }
    public function field_model() { echo '<input type="text" name="writeman_ai_model" value="'.esc_attr(get_option('writeman_ai_model','llama-3.3-70b-versatile')).'" class="regular-text"><p class="description">Groq: llama-3.3-70b-versatile | deepseek-r1-distill-llama-70b. OpenRouter: deepseek/deepseek-chat</p>'; }
    public function field_fallback_model() { echo '<input type="text" name="writeman_ai_fallback_model" value="'.esc_attr(get_option('writeman_ai_fallback_model','gemma2-9b-it')).'" class="regular-text">'; }
    public function field_test() { echo '<button type="button" id="wm-test-ai-btn" class="button">🔌 Probar conexión con IA</button> <span id="wm-ai-test-result"></span>'; }
    public function field_max_posts() { echo '<input type="number" name="writeman_max_posts_per_run" value="'.esc_attr(get_option('writeman_max_posts_per_run',3)).'">'; }
    public function field_post_status() { $s=get_option('writeman_post_status','publish'); echo '<select name="writeman_post_status"><option value="publish" '.selected($s,'publish',false).'>Publicado</option><option value="draft" '.selected($s,'draft',false).'>Borrador</option></select>'; }
    public function field_post_format() { $f=get_option('writeman_post_format','standard'); echo '<select name="writeman_post_format"><option value="standard" '.selected($f,'standard',false).'>Estándar</option></select>'; }
    public function field_comments() { $v=get_option('writeman_allow_comments',1); echo '<label><input type="radio" name="writeman_allow_comments" value="1" '.checked(1,$v,false).'> Sí</label> <label><input type="radio" name="writeman_allow_comments" value="0" '.checked(0,$v,false).'> No</label>'; }
    public function field_pings() { $v=get_option('writeman_allow_pings',1); echo '<label><input type="radio" name="writeman_allow_pings" value="1" '.checked(1,$v,false).'> Sí</label> <label><input type="radio" name="writeman_allow_pings" value="0" '.checked(0,$v,false).'> No</label>'; }
    
    public function sanitize_feeds($input) {
        $new = []; if (is_array($input)) foreach ($input as $f) if (!empty($f['url'])) $new[] = ['url'=>esc_url_raw($f['url']), 'categories'=>isset($f['categories'])?array_map('intval',(array)$f['categories']):[], 'tags'=>isset($f['tags'])?array_map('sanitize_text_field',explode(',',$f['tags'])):[], 'author'=>intval($f['author']), 'featured_image'=>esc_url_raw($f['featured_image']), 'language'=>sanitize_text_field($f['language']??'es')];
        return $new;
    }
    public function sanitize_cron_schedule($i) { $d=['type'=>'interval','minutes'=>5,'hours'=>[0],'days_of_week'=>[1],'days_of_month'=>[1]]; if(!is_array($i)) return $d; return ['type'=>in_array($i['type'],['interval','daily','weekly','monthly'])?$i['type']:'interval', 'minutes'=>max(1,intval($i['minutes'])), 'hours'=>isset($i['hours'])?array_map('intval',(array)$i['hours']):[0], 'days_of_week'=>isset($i['days_of_week'])?array_map('intval',(array)$i['days_of_week']):[1], 'days_of_month'=>isset($i['days_of_month'])?array_map('intval',(array)$i['days_of_month']):[1]]; }
    
    public function render_feeds_ui() {
        $feeds = get_option('writeman_feeds',[]); if(empty($feeds)) $feeds = [['url'=>'','categories'=>[],'tags'=>[],'author'=>0,'featured_image'=>'','language'=>'es']];
        $cats = get_categories(['hide_empty'=>false]); $authors = get_users(['capability'=>'publish_posts']);
        echo '<div id="feeds-container">'; foreach($feeds as $idx=>$f) $this->render_feed_item($idx,$f,$cats,$authors); echo '</div><button type="button" id="add-feed-btn" class="button">+ Añadir fuente</button>';
    }
    private function render_feed_item($idx,$f,$cats,$authors) {
        $langs = ['es'=>'Español','en'=>'English','fr'=>'Français','de'=>'Deutsch','pt'=>'Português']; $cur=$f['language']??'es';
        echo '<div class="feed-item" style="border:1px solid #ccc; margin-bottom:20px; padding:15px;">';
        echo '<h4>Fuente #'.($idx+1).'</h4>';
        echo '<input type="url" name="writeman_feeds['.$idx.'][url]" value="'.esc_attr($f['url']).'" style="width:100%;">';
        echo '<button type="button" class="button test-feed-button" data-url="'.esc_attr($f['url']).'" style="margin:10px 0;">📡 Probar esta fuente</button> <span class="test-feed-result"></span>';
        echo '<label>Idioma:</label><select name="writeman_feeds['.$idx.'][language]">'; foreach($langs as $k=>$v) echo '<option value="'.$k.'" '.selected($cur,$k,false).'>'.$v.'</option>'; echo '</select>';
        echo '<label>Categorías:</label><select multiple name="writeman_feeds['.$idx.'][categories][]" style="width:100%; height:100px;">'; foreach($cats as $c) echo '<option value="'.$c->term_id.'" '.(in_array($c->term_id,$f['categories'])?'selected':'').'>'.esc_html($c->name).'</option>'; echo '</select>';
        echo '<label>Etiquetas (coma):</label><input type="text" name="writeman_feeds['.$idx.'][tags]" value="'.esc_attr(implode(',',$f['tags'])).'">';
        echo '<label>Autor:</label><select name="writeman_feeds['.$idx.'][author]">'; echo '<option value="0">- Seleccionar -</option>'; foreach($authors as $a) echo '<option value="'.$a->ID.'" '.selected($f['author'],$a->ID,false).'>'.esc_html($a->display_name).'</option>'; echo '</select>';
        echo '<label>Imagen destacada fija (URL):</label><input type="url" name="writeman_feeds['.$idx.'][featured_image]" value="'.esc_attr($f['featured_image']).'">';
        echo '<button type="button" class="button remove-feed">Eliminar</button>';
        echo '</div>';
    }
    
    public function render_cron_ui() { 
        $s = get_option('writeman_cron_schedule',['type'=>'interval','minutes'=>5,'hours'=>[0],'days_of_week'=>[1],'days_of_month'=>[1]]); 
        echo '<select name="writeman_cron_schedule[type]" id="cron-type">';
        echo '<option value="interval" '.selected($s['type'],'interval',false).'>Cada X minutos</option>';
        echo '<option value="daily" '.selected($s['type'],'daily',false).'>Diario (horas)</option>';
        echo '<option value="weekly" '.selected($s['type'],'weekly',false).'>Semanal (días)</option>';
        echo '<option value="monthly" '.selected($s['type'],'monthly',false).'>Mensual (días)</option>';
        echo '</select>';
        echo '<div id="interval-opts"><label>Minutos: <input type="number" name="writeman_cron_schedule[minutes]" value="'.$s['minutes'].'"></label></div>';
        echo '<div id="hours-opts"><strong>Horas:</strong><br>';
        for($i=0;$i<24;$i++) echo '<label><input type="checkbox" name="writeman_cron_schedule[hours][]" value="'.$i.'" '.checked(in_array($i,$s['hours']),true,false).'> '.$i.'</label> ';
        echo '</div><div id="weekdays-opts"><strong>Días semana:</strong><br>';
        $dow=['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
        foreach($dow as $d=>$n) echo '<label><input type="checkbox" name="writeman_cron_schedule[days_of_week][]" value="'.$d.'" '.checked(in_array($d,$s['days_of_week']),true,false).'> '.$n.'</label> ';
        echo '</div><div id="monthdays-opts"><strong>Días mes:</strong><br>';
        for($d=1;$d<=31;$d++) echo '<label><input type="checkbox" name="writeman_cron_schedule[days_of_month][]" value="'.$d.'" '.checked(in_array($d,$s['days_of_month']),true,false).'> '.$d.'</label> ';
        echo '</div>';
        echo '<script>jQuery(function($){function t(){var v=$("#cron-type").val();$("#interval-opts").toggle(v==="interval");$("#hours-opts").toggle(v!=="interval");$("#weekdays-opts").toggle(v==="weekly");$("#monthdays-opts").toggle(v==="monthly");}$("#cron-type").change(t);t();});</script>';
    }
    
    public function show_admin_notices() { 
        $n = get_transient('writeman_sync_notice'); 
        if($n){ echo '<div class="notice notice-success"><p>'.esc_html($n).'</p></div>'; delete_transient('writeman_sync_notice'); } 
        // Mensajes de guardado en programación
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] && isset($_GET['page']) && $_GET['page'] == 'writeman' && isset($_GET['tab']) && $_GET['tab'] == 'programacion') {
            echo '<div class="notice notice-success is-dismissible"><p>✅ Configuración de programación guardada correctamente.</p></div>';
        }
    }
    
    public function render_page() {
        $this->active_tab = isset($_GET['tab'])?$_GET['tab']:'inicio'; if(!in_array($this->active_tab,$this->tabs)) $this->active_tab='inicio';
        ?>
        <div class="wrap"><h1>WriteMan v1.0.2 - Generador de Noticias IA</h1>
        <h2 class="nav-tab-wrapper"><?php foreach($this->tabs as $t): ?><a href="?page=writeman&tab=<?php echo $t; ?>" class="nav-tab <?php echo $this->active_tab===$t?'nav-tab-active':''; ?>"><?php echo ucfirst($t); ?></a><?php endforeach; ?></h2>
        <?php if($this->active_tab === 'inicio'): ?>
            <div style="background:#fff; padding:20px; border-left:4px solid #2271b1;">
                <h2>👋 ¡Hola! Soy <strong>Jota</strong>, creador de <strong>20xxnoticias.com</strong></h2>
                <h3>WriteMan 1.0.2 – Generación autónoma de noticias con IA</h3>
                <p><strong>¿Qué ofrece WriteMan?</strong></p>
                <ul><li>🤖 Artículos extensos (900+ palabras) con IA via Groq/OpenRouter</li><li>📰 Curaduría desde múltiples fuentes RSS</li><li>📊 Predicción de viralidad</li><li>🧪 A/B testing de títulos</li><li>📈 Aprendizaje automático</li><li>🌐 5 idiomas</li><li>🖼️ Imagen destacada automática (con imagen por defecto)</li></ul>
                <p>👉 <strong>Ver en acción:</strong> <a href="<?php echo home_url('/news'); ?>" target="_blank">News</a></p>
                <hr>
                <div class="actions"><button id="sync-feeds-btn" class="button button-primary">🔄 Sincronizar fuentes</button> <button id="run-queue-btn" class="button">▶ Ejecutar cola ahora</button> <button id="test-feed-btn" class="button">📡 Probar primera fuente</button> <button id="clear-queue-btn" class="button" style="background:#d63638; color:#fff;">🗑️ Limpiar cola</button> <span id="action-msg"></span></div>
            </div>
            <div class="stats-box"><?php $this->render_stats(); ?></div>
        <?php elseif($this->active_tab === 'cola'): ?>
            <div><div><button id="refresh-queue-btn" class="button">🔄 Refrescar</button> <button id="run-queue-btn-2" class="button button-primary">▶ Ejecutar cola</button> <button id="clear-queue-btn-2" class="button" style="background:#d63638;color:#fff;">🗑️ Limpiar cola</button></div>
            <p><a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=writeman_download_log'), 'writeman_download_log'); ?>" class="button">📄 Descargar log</a></p>
            <div id="queue-stats"></div>
            <?php $this->render_queue_table(); ?>
            </div>
        <?php else: ?>
            <form method="post" action="options.php"><?php settings_fields('writeman_'.$this->active_tab); do_settings_sections('writeman_'.$this->active_tab); submit_button(); ?></form>
        <?php endif; ?>
        </div>
        <?php $this->inline_js(); ?>
        <?php
    }
    
    private function inline_js() {
        ?>
        <script>
        jQuery(function($){
            function showMsg(t,e){ $('#action-msg').html('<span style="color:'+(e?'red':'green')+';">'+t+'</span>'); setTimeout(function(){ $('#action-msg').html(''); },5000); }
            $('#sync-feeds-btn').click(function(){ var b=$(this); b.prop('disabled',true).text('...'); $.post(writeman_ajax.ajax_url,{action:'writeman_sync_feeds',nonce:writeman_ajax.nonce},function(r){ if(r.success) showMsg(r.data.message); else showMsg(r.data,true); b.prop('disabled',false).text('🔄 Sincronizar fuentes'); }); });
            $('#run-queue-btn, #run-queue-btn-2').click(function(){ var b=$(this); b.prop('disabled',true).text('Procesando...'); $.post(writeman_ajax.ajax_url,{action:'writeman_run_now',nonce:writeman_ajax.nonce},function(r){ if(r.success) showMsg(r.data.message); else showMsg(r.data,true); b.prop('disabled',false).text('▶ Ejecutar cola'); }); });
            $('#test-feed-btn').click(function(){ var b=$(this); b.prop('disabled',true).text('...'); $.post(writeman_ajax.ajax_url,{action:'writeman_test_feed',nonce:writeman_ajax.nonce},function(r){ if(r.success) showMsg(r.data); else showMsg(r.data,true); b.prop('disabled',false).text('📡 Probar primera fuente'); }); });
            $('#clear-queue-btn, #clear-queue-btn-2').click(function(){ if(!confirm('¿Eliminar todos los pendientes y fallidos?')) return; var b=$(this); b.prop('disabled',true).text('...'); $.post(writeman_ajax.ajax_url,{action:'writeman_clear_queue',nonce:writeman_ajax.nonce},function(r){ if(r.success) showMsg(r.data); else showMsg(r.data,true); b.prop('disabled',false).text('🗑️ Limpiar cola'); setTimeout(function(){ location.reload(); },1500); }); });
            $('#refresh-queue-btn').click(function(){ $.post(ajaxurl,{action:'writeman_refresh_queue'},function(r){ $('#queue-stats').html(r); }); });
            $('#wm-test-ai-btn').click(function(){ var b=$(this); b.prop('disabled',true).text('Probando...'); $('#wm-ai-test-result').html(''); $.post(writeman_ajax.ajax_url,{action:'writeman_test_ai_connection',nonce:writeman_ajax.nonce},function(r){ if(r.success) $('#wm-ai-test-result').html('<span style="color:green;">✅ '+r.data+'</span>'); else $('#wm-ai-test-result').html('<span style="color:red;">❌ '+r.data+'</span>'); b.prop('disabled',false).text('🔌 Probar conexión con IA'); }); });
            $('#add-feed-btn').click(function(e){ e.preventDefault(); var idx=$('.feed-item').length; var newItem=$('.feed-item:first').clone(); newItem.find('input,select,textarea').val(''); newItem.find('input[name*="[url]"]').attr('name','writeman_feeds['+idx+'][url]'); newItem.find('select[name*="[language]"]').attr('name','writeman_feeds['+idx+'][language]'); newItem.find('select[name*="[categories]"]').attr('name','writeman_feeds['+idx+'][categories][]'); newItem.find('input[name*="[tags]"]').attr('name','writeman_feeds['+idx+'][tags]'); newItem.find('select[name*="[author]"]').attr('name','writeman_feeds['+idx+'][author]'); newItem.find('input[name*="[featured_image]"]').attr('name','writeman_feeds['+idx+'][featured_image]'); newItem.find('h4').text('Fuente #'+(idx+1)); $('#feeds-container').append(newItem); });
            $(document).on('click','.remove-feed',function(e){ e.preventDefault(); $(this).closest('.feed-item').remove(); });
            $(document).on('click','.test-feed-button',function(){ var btn=$(this); var url=btn.data('url'); btn.prop('disabled',true).text('Probando...'); btn.siblings('.test-feed-result').html(''); $.post(writeman_ajax.ajax_url,{action:'writeman_test_specific_feed',nonce:writeman_ajax.nonce,url:url},function(r){ if(r.success) btn.siblings('.test-feed-result').html('<span style="color:green;">✅ '+r.data+'</span>'); else btn.siblings('.test-feed-result').html('<span style="color:red;">❌ '+r.data+'</span>'); btn.prop('disabled',false).text('📡 Probar esta fuente'); }); });
        });
        </script>
        <?php
    }
    
    private function render_stats() {
        global $wpdb; $ana = $wpdb->prefix.'writeman_analytics';
        $top = $wpdb->get_results("SELECT source_feed_url, AVG(ctr) as avg_ctr, SUM(views) as views FROM $ana WHERE source_feed_url!='' GROUP BY source_feed_url ORDER BY avg_ctr DESC LIMIT 5");
        echo '<div><h4>🏆 Mejores fuentes (CTR)</h4><ul>'; foreach($top as $t) echo '<li>'.esc_html($t->source_feed_url).' – '.round($t->avg_ctr*100,1).'% ('.$t->views.' vistas)</li>'; echo '</ul></div>';
        $kw = get_option('writeman_hot_keywords',[]); echo '<div><h4>🔥 Keywords populares</h4><ul>'; foreach(array_slice($kw,0,10) as $k) echo '<li>'.esc_html($k).'</li>'; echo '</ul></div>';
        $posts = $wpdb->get_results("SELECT post_id, SUM(views) as views FROM $ana GROUP BY post_id ORDER BY views DESC LIMIT 5"); echo '<div><h4>📰 Artículos más vistos</h4><ul>'; foreach($posts as $p){ $title = get_the_title($p->post_id); echo '<li><a href="'.get_permalink($p->post_id).'">'.esc_html($title).'</a> – '.$p->views.' vistas</li>'; } echo '</ul></div>';
    }
    
    private function render_queue_table() {
        global $wpdb; $table = $wpdb->prefix.'writeman_queue';
        $p = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='pending'");
        $c = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='completed'");
        $f = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status='failed'");
        echo "<h3>📋 Estado de la cola</h3><ul><li>⏳ Pendientes: $p</li><li>✅ Completados: $c</li><li>❌ Fallidos: $f</li></ul>";
        $recent = $wpdb->get_results("SELECT * FROM $table ORDER BY created_at DESC LIMIT 20");
        echo "<table class='widefat'><thead><tr><th>ID</th><th>Título</th><th>Fuente</th><th>Estado</th><th>Viral</th><th>Post ID</th><th>Error</th><th>Creado</th></tr></thead><tbody>";
        foreach ($recent as $r) {
            $title = '-'; $source = '-';
            if ($r->generated_post_id) {
                $title = get_the_title($r->generated_post_id);
                $source = get_post_meta($r->generated_post_id, '_writeman_source_feed', true);
            } else {
                $g = json_decode($r->group_data, true);
                $title = $g['title'] ?? 'Sin título';
                $source = $g['source_feed'] ?? '-';
            }
            $err = $r->error_message ? esc_html(substr($r->error_message,0,80)) : '-';
            echo "<tr><td>{$r->id}</td><td>" . esc_html($title) . "</td><td>" . esc_html($source) . "</td><td>{$r->status}</td><td>{$r->viral_score}</td><td>{$r->generated_post_id}</td><td>{$err}</td><td>{$r->created_at}</td></tr>";
        }
        echo "</tbody> </table>";
    }
    
    // AJAX
    public function ajax_sync_feeds() { check_ajax_referer('writeman_nonce','nonce'); if(!class_exists('WriteMan_Worker')) wp_send_json_error('Worker no encontrado'); $stats = WriteMan_Worker::populate_queue(true); if(is_array($stats)) wp_send_json_success(['message'=>"📰 Artículos: {$stats['total_items']} | ✅ Calidad: {$stats['passed_quality']} | 📦 Encolados: {$stats['added']}"]); else wp_send_json_success(['message'=>"Sincronizado: $stats grupos."]); }
    public function ajax_run_now() { check_ajax_referer('writeman_nonce','nonce'); if(!class_exists('WriteMan_Worker')) wp_send_json_error('Worker missing'); WriteMan_Worker::process_queue(); wp_send_json_success(['message'=>'✅ Cola procesada. Revisa tus posts.']); }
    public function ajax_test_feed() { check_ajax_referer('writeman_nonce','nonce'); $feeds = get_option('writeman_feeds',[]); if(empty($feeds)) wp_send_json_error('No hay fuentes'); $first = $feeds[0]; $items = WriteMan_RSS_Fetcher::fetch_feed($first['url']); if(empty($items)) wp_send_json_error('No se obtuvieron artículos'); $item = $items[0]; $score = WriteMan_Curator::score_item($item); if(!$score['passed']) wp_send_json_error('Calidad baja (score: '.$score['score'].')'); $group = WriteMan_Grouping::prepare_group_data([$item]); $group['feed_metadata'] = $first; $viral = WriteMan_Viral_Predictor::predict($group); if(!$viral['should_proceed']) wp_send_json_error('Viralidad baja'); $id = WriteMan_Queue::add($group, $viral['score']); if($id) wp_send_json_success('Artículo encolado. Viral: '.$viral['score']); else wp_send_json_error('Error al encolar'); }
    public function ajax_test_specific_feed() { check_ajax_referer('writeman_nonce','nonce'); $url = sanitize_text_field($_POST['url']); if(empty($url)) wp_send_json_error('URL vacía.'); $items = WriteMan_RSS_Fetcher::fetch_feed($url); if(empty($items)) wp_send_json_error('No se obtuvieron artículos.'); else { $first = $items[0]; wp_send_json_success(sprintf('✅ Fuente válida. Último artículo: "%s"', esc_html($first['title']))); } }
    public function ajax_refresh_queue() { $this->render_queue_table(); wp_die(); }
    public function ajax_clear_queue() { check_ajax_referer('writeman_nonce','nonce'); global $wpdb; $table = $wpdb->prefix.'writeman_queue'; $wpdb->query("DELETE FROM $table WHERE status IN ('pending','failed')"); $wpdb->query("UPDATE $table SET status = 'pending' WHERE status = 'processing'"); wp_send_json_success('Cola limpiada.'); }
    public function ajax_test_ai_connection() { check_ajax_referer('writeman_nonce','nonce'); if(!class_exists('WriteMan_AI_Generator')) wp_send_json_error('Clase AI no disponible'); $res = WriteMan_AI_Generator::test_connection(); if($res['success']) wp_send_json_success($res['message']); else wp_send_json_error($res['message']); }
    public function ajax_download_log() { if(!current_user_can('manage_options')) wp_die('No autorizado'); check_admin_referer('writeman_download_log'); $upload_dir = wp_upload_dir(); $log = $upload_dir['basedir'].'/writeman-debug.log'; if(file_exists($log)){ header('Content-Type: text/plain'); header('Content-Disposition: attachment; filename="writeman-debug.log"'); readfile($log); exit; } wp_die('No hay log.'); }
}