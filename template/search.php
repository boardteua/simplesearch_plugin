<div 
    id="app" 
    class="ss_search <?= $atts['post-type'] ?>" 
    data-post-type="<?= $atts['post-type'] ?>"  
    data-element-count="<?= $elementCount ?>"
    data-element-view="<?= $view ?>">
    <div class="ss__search-box">
        <input 
            type="text"
            v-model="searchText"  
            @keyup.enter="search" 
            placeholder="Enter your search query" 
            class="ss__input">
        <button @click="search"><?= __('Search', 'ss'); ?></button>
        <div 
            v-if="errorMessage" 
            class="error-message"
        >{{ errorMessage }}</div>
        <button 
            class="ss__grid-toogle" 
            @click="toggleGrid">
            <?= __('Toggle Grid', 'ss') ?></button>
    </div>
    <div 
        class="ss__wrapper" 
        :class="{ 'grid': isGridEnabled || viewType === 'grid' }">
        <div 
            v-for="result in results" 
            :key="result.link"  
            class="ss__item" >
               <h3><a :href="result.link">{{ result.title }}</a></h3>
               <p v-html="result.content"></p>
        </div>
    </div>
        <button 
            v-if="currentPage <= pages" 
            @click="loadMore">
            <?= __('Load More', 'ss'); ?></button>
    </div>  
