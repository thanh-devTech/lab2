<style>
    h1,
    h4 {
        margin: unset;
    }

    h1 {
        margin-bottom: 5px !important;
    }

    h4 {
        margin-bottom: 5px;
    }

    h5 {
        font-weight: var(--font-weight-medium);
        margin: 0px;
        --tw-font-weight: var(--font-weight-medium);
        font-size: 13px;
        margin-bottom: 10.5px;
    }

    h1,
    h2,
    h3,
    h4,
    h5,
    h6 {
        font-weight: unset !important;
        font-size: inherit;
    }

    h4 {
        font-size: 14.5px;
    }

    p {
        margin-bottom: 10px;
    }

    p strong {
        color: var(--color-gray-700);
    }


    .post_content h1,
    .post_content h2,
    .post_content h3,
    .post_content h4,
    .post_content h5,
    .post_content h6 {
        font-size: unset;
        font-style: unset;
        font-weight: bold !important;
    }

    .post_content h1 {
        font-size: 2em;
        font-weight: 700;
        margin: 0.67em 0;
    }

    .post_content h2 {
        font-size: 1.5em;
        font-weight: 700;
        margin: 0.75em 0;
    }

    .post_content h3 {
        font-size: 1.17em;
        font-weight: 700;
        margin: 0.83em 0;
    }

    .post_content h4 {
        font-size: 1em;
        font-weight: 700;
        margin: 1em 0;
    }

    .post_content h5 {
        font-size: 0.83em;
        font-weight: 700;
        margin: 1.5em 0;
    }

    .post_content h6 {
        font-size: 0.67em;
        font-weight: 700;
        margin: 1.67em 0;
    }
</style>
<div class="wrap">
    <div class="flex-1 overflow-auto p-4 sm:p-6 lg:p-8">
        <div class="space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-0">
                <div>
                    <h1 class="text-xl sm:text-2xl font-semibold">Release Notes</h1>
                    <p class="text-muted-foreground mt-1 sm:text-[13px]">View version history and updates for Ignite ABX
                    </p>
                </div>
            </div>
            <div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <?php
                    $args = array(
                        'post_type' => 'release_note',
                        'posts_per_page' => -1,
                        'post_status' => 'publish'
                    );

                    $notes_main = get_posts($args);
                    foreach ($notes_main as $index => $post) {
                        $version_field = get_post_meta($post->ID, '_version', true);
                        $guide_field = get_post_meta($post->ID, '_guide', true);
                    ?>
                        <div id="p_notes_<?php echo $post->ID; ?>" <?php if ($index != 0) { ?> style="display:none"
                            <?php } ?> class="post-note-item lg:col-span-2">
                            <div data-slot="card"
                                class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                                <div data-slot="card-header"
                                    class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                    <div class="flex items-center justify-between">
                                        <h4 data-slot="card-title" class="leading-none flex items-center gap-2"><svg
                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                stroke-linecap="round" stroke-linejoin="round"
                                                class="lucide lucide-sparkles h-5 w-5 text-blue-500" aria-hidden="true">
                                                <path
                                                    d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z">
                                                </path>
                                                <path d="M20 2v4"></path>
                                                <path d="M22 4h-4"></path>
                                                <circle cx="4" cy="20" r="2"></circle>
                                            </svg>Version <?php echo $version_field; ?></h4><span
                                            class="text-sm text-muted-foreground"><?php echo get_the_date('F j, Y', $post->ID); ?></span>
                                    </div>
                                </div>

                                <div id="p_content_note_<?php echo $post->ID; ?>"
                                    <?php if ($index !== 0) { ?>style="display:none" <?php } ?> data-slot="card-content"
                                    class="pb-6 post_content px-6 [&amp;:last-child]:pb-6">
                                    <?php echo apply_filters('the_content', $post->post_content); ?>
                                    <div class="pt-4 mt-6 border-t text-sm text-gray-600">
                                        <p>For technical support or questions about this release, please contact our support
                                            team through the Contact Us page.</p>
                                    </div>
                                </div>

                                <div id="p_content_guide_<?php echo $post->ID; ?>" style="display:none"
                                    data-slot="card-content" class="post_content pb-6 px-6 [&amp;:last-child]:pb-6">
                                    <?php echo wpautop($guide_field); ?>
                                    <div class="pt-4 mt-6 border-t text-sm text-gray-600">
                                        <p>For technical support or questions about this release, please contact our support
                                            team through the Contact Us page.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="lg:col-span-1">
                        <div data-slot="card"
                            class="bg-card text-card-foreground flex flex-col gap-6 rounded-xl border shadow-card">
                            <div data-slot="card-header"
                                class="@container/card-header grid auto-rows-min grid-rows-[auto_auto] items-start gap-1.5 px-6 pt-6 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-6">
                                <h4 data-slot="card-title" class="leading-none">Recent Versions</h4>
                                <p class="text-xs text-gray-600 mt-1">Click on any version to view release notes</p>
                            </div>
                            <div data-slot="card-content" class="px-6 [&amp;:last-child]:pb-6">
                                <div class="space-y-3">
                                    <?php
                                    $args = array(
                                        'post_type' => 'release_note',
                                        'posts_per_page' => -1,
                                        'post_status' => 'publish'
                                    );

                                    $notes = get_posts($args);
                                    foreach ($notes as $index => $post) {
                                        $guide_field = get_post_meta($post->ID, '_guide', true);
                                        $version_field = get_post_meta($post->ID, '_version', true);
                                    ?>
                                        <div id="m_<?php echo $post->ID; ?>" post_id="<?php echo $post->ID; ?>"
                                            class="box_post_notes_sidebar p-4 border rounded-lg transition-all <?php if ($index == 0) { ?>border-primary bg-primary/5<?php } ?> shadow-md">
                                            <div class="space-y-3">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span
                                                        class="font-medium text-gray-900">v<?php echo $version_field; ?></span>
                                                    <?php if ($index == 0) { ?><span data-slot="badge"
                                                            class="inline-flex items-center justify-center rounded-md border px-2 py-0.5 font-medium w-fit whitespace-nowrap shrink-0 [&amp;&gt;svg]:size-3 gap-1 [&amp;&gt;svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden border-transparent [a&amp;]:hover:bg-primary/90 bg-blue-500 text-white text-xs">Latest</span><?php } ?>
                                                </div>
                                                <div>
                                                    <p class="text-sm text-gray-900 mb-1"><?php echo $post->post_title; ?>
                                                    </p>
                                                    <p class="text-xs text-gray-600">
                                                        <?php
                                                        $excerpt = get_the_excerpt($post->ID);
                                                        if ($excerpt != '') {
                                                            if (mb_strlen($excerpt) > 250) {
                                                                $excerpt = mb_substr($excerpt, 0, 250) . '...';
                                                            }
                                                            echo $excerpt;
                                                        } else {
                                                            $content = strip_tags($post->post_content);
                                                            if (mb_strlen($content) > 250) {
                                                                $content = mb_substr($content, 0, 250) . '...';
                                                            }
                                                            echo $content;
                                                        }
                                                        ?>
                                                    </p>
                                                </div>
                                                <div class="text-xs text-gray-500 mb-2">
                                                    <?php echo get_the_date('F j, Y', $post->ID); ?>
                                                </div>
                                                <div class="flex gap-2">
                                                    <button data-slot="button" post_id="<?php echo $post->ID; ?>"
                                                        class="btn-note inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 <?php if ($index == 0) { ?>bg-primary hover:bg-primary/90 text-white <?php } else { ?>bg-white hover:bg-gray-50 text-gray-700 border border-gray-300<?php } ?> flex-1"><svg
                                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                            viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                            stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                            class="lucide lucide-file-text h-4 w-4 mr-1" aria-hidden="true">
                                                            <path
                                                                d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z">
                                                            </path>
                                                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                                            <path d="M10 9H8"></path>
                                                            <path d="M16 13H8"></path>
                                                            <path d="M16 17H8"></path>
                                                        </svg>Notes
                                                    </button>

                                                    <?php
                                                    if ($guide_field != '') {
                                                    ?>
                                                        <button data-slot="button" post_id="<?php echo $post->ID; ?>"
                                                            class="btn-guide inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 flex-1"><svg
                                                                xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                                                viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                                                stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                                                class="lucide lucide-book-open h-4 w-4 mr-1" aria-hidden="true">
                                                                <path d="M12 7v14"></path>
                                                                <path
                                                                    d="M3 18a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h5a4 4 0 0 1 4 4 4 4 0 0 1 4-4h5a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1h-6a3 3 0 0 0-3 3 3 3 0 0 0-3-3z">
                                                                </path>
                                                            </svg>Guide
                                                        </button>
                                                    <?php } ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                    }
                                    ?>
                                </div>
                                <div class="mt-6 pt-4 border-t">
                                    <p class="text-xs text-gray-600 mb-3">Looking for older versions? View the complete
                                        version history in our documentation.</p><button data-slot="button"
                                        class="inline-flex items-center justify-center whitespace-nowrap text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&amp;_svg]:pointer-events-none [&amp;_svg:not([class*='size-'])]:size-4 shrink-0 [&amp;_svg]:shrink-0 outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive border bg-background text-foreground hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 h-8 rounded-md gap-1.5 px-3 has-[&gt;svg]:px-2.5 w-full"><svg
                                            xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-file-text h-4 w-4 mr-2" aria-hidden="true">
                                            <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"></path>
                                            <path d="M14 2v4a2 2 0 0 0 2 2h4"></path>
                                            <path d="M10 9H8"></path>
                                            <path d="M16 13H8"></path>
                                            <path d="M16 17H8"></path>
                                        </svg>View All Versions</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 pt-4 pb-2 text-xs text-muted-foreground border-t">
                    <p><strong style="color:unset">Note:</strong> Release notes are provided for informational purposes.
                        All updates are
                        automatically applied to your Ignite ABX system. For questions about specific features or
                        changes,
                        please contact our support team. Version numbers and release dates are examples for
                        demonstration
                        purposes.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    jQuery(function($) {

        function sel_m(id_menu) {
            $(".box_post_notes_sidebar").removeClass("border-primary bg-primary/5");
            $(".box_post_notes_sidebar").find(".btn-note").removeClass("bg-primary hover:bg-primary/90 text-white");
            $(".box_post_notes_sidebar").find(".btn-note").addClass(
                "bg-white hover:bg-gray-50 text-gray-700 border border-gray-300");
            $(".box_post_notes_sidebar").find(".btn-guide").removeClass(
                "bg-primary hover:bg-primary/90 text-white hover:text-accent-foreground");
            $(".box_post_notes_sidebar").find(".btn-guide").addClass(
                "bg-white hover:bg-gray-50 text-gray-700 border border-gray-300 hover:text-accent-foreground");
            $("#m_" + id_menu).addClass("border-primary bg-primary/5");
            $("#m_" + id_menu).find(".btn-note").removeClass(
                "bg-white hover:bg-gray-50 text-gray-700 border border-gray-300");
            $("#m_" + id_menu).find(".btn-note").addClass("bg-primary hover:bg-primary/90 text-white");
        }

        $(".box_post_notes_sidebar").click(function() {
            var post_id = $(this).attr("post_id");
            $(".post-note-item").hide();
            $("#p_notes_" + post_id).show();
            $("#p_content_note_" + post_id).show();
            sel_m(post_id);
        });

        $(".btn-guide").click(function() {
            var post_id = $(this).attr("post_id");
            $(".post-note-item").hide();
            $("#p_notes_" + post_id).show();
            $("#p_content_guide_" + post_id).show();
            $("#p_content_note_" + post_id).hide();
            sel_m(post_id);
            $("#m_" + post_id).find(".btn-note").removeClass("bg-primary hover:bg-primary/90 text-white");
            $("#m_" + post_id).find(".btn-note").addClass(
                "bg-white hover:bg-gray-50 text-gray-700 border border-gray-300");
            $("#m_" + post_id).find(".btn-guide").addClass("bg-primary hover:bg-primary/90 text-white");
            $("#m_" + post_id).find(".btn-guide").removeClass(
                "bg-white hover:bg-gray-50 text-gray-700 border border-gray-300");
            return false;
        });

        $(".btn-note").click(function() {
            var post_id = $(this).attr("post_id");
            $(".post-note-item").hide();
            $("#p_notes_" + post_id).show();
            $("#p_content_guide_" + post_id).hide();
            $("#p_content_note_" + post_id).show();
            sel_m(post_id);
            return false;
        });
    });
</script>
