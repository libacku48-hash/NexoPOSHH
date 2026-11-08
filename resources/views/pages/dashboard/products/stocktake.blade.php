@extends( 'layout.dashboard' )

@section( 'layout.dashboard.body' )
    <div>
        @include( '/common/dashboard-header' ) 
        <div class="px-4 flex-auto flex flex-col" id="dashboard-content">
            @include( 'common.dashboard.title' )
            <ns-stocktake :stocktake='@json( $stocktake )'></ns-stocktake>
        </div>
    </div>
@endsection
