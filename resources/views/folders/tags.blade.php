<table class="custom-table" style="width: 100%">
    <thead>
        <tr>
            <th>Category</th>
            <th>Tags</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($categories as $category)
            <tr>
                <td> <i class="fa fa-arrows mt-2"></i>{{ $category->name }}</td>
                <td>
                    @foreach ($category->tags as $tag)
                        <label for="" class="badge badge-danger text-info">{{ $tag->name }}</label>
                    @endforeach
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
