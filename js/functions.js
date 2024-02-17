document.querySelectorAll(".ss_search").forEach((app) => {
 new Vue({
  el: app,
  data: {
   results: [],
   currentPage: 1,
   pages: 0,
   totalPages: 1,
   searchText: "",
   count: null,
   isGridEnabled: false,
   errorMessage: "",
  },
  computed: {
   elementsCount() {
    return this.$el.getAttribute("data-element-count") ?? 6;
   },
   postType() {
    return this.$el.getAttribute("data-post-type") ?? "post";
   },
   viewType() {
    return this.$el.getAttribute("data-element-view") ?? "list";
   },
  },
  mounted() {
   console.log(this.viewType);
   if ("grid" === this.viewType) {
    this.isGridEnabled = true;
   }
  },
  methods: {
   fetchResults() {
    // Let`s do some POST /wp-json/ss/v1/search
    fetch(`${ss_obj.url}/wp-json/${ss_obj.prefix}/v1/search`, {
     method: "POST",
     headers: {
      "Content-Type": "application/json",
     },
     body: JSON.stringify({
      text: this.searchText,
      count: this.elementsCount,
      page: this.currentPage,
      type: this.postType,
     }),
    })
     .then((response) => {
      if (response.status === 404) {
       this.errorMessage = ss_obj.noItems;
       this.results = [];
      } else {
       // Скидання повідомлення про помилку
       this.errorMessage = "";
       return response.json();
      }
     })
     .then((data) => {
      // Оновлюємо дані
      if (data.info.last) {
       this.last = true;
      }

      const totalCount = data.info.total;
      const perPage = this.count;
      this.results.push(...data.items);
      this.currentPage++;
      this.totalPages = Math.ceil(totalCount / perPage);
      this.pages = data.info.pages;
     });
   },
   search() {
    if (!this.searchText.trim()) {
     this.errorMessage = ss_obj.emptyReq;
     return;
    }
    // Перевірка на ін'єкції
    if (/^[a-zA-Z0-9а-яА-щА-ЩЬьЮюЯяЇїІіЄєҐґЯЁё\s]*$/.test(this.searchText)) {
     // Скидуємо лічильник сторінок при новому пошуковому запиті

     this.results = [];
     this.currentPage = 1;
     this.totalPages = 1;
     this.fetchResults();
    } else {
     this.errorMessage = ss_obj.symbolError;
     return;
    }
   },
   loadMore() {
    this.fetchResults();
   },
   toggleGrid() {
    this.isGridEnabled = !this.isGridEnabled;
   },
  },
 });
});
