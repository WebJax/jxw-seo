/**
 * LocalSEO Booster – Data Center admin UI
 *
 * Plain JavaScript (ES6+, no JSX, no build step).
 * Relies on WordPress-bundled globals:  wp.element, wp.components,
 * wp.i18n, wp.apiFetch.  All are declared as script dependencies in
 * class-admin.php so they are guaranteed to be available.
 */
/* global wp, localSEOData */
(function () {
    'use strict';

    /* ── WordPress globals ─────────────────────────────────────────── */
    const {
        createElement: el,
        useState,
        useEffect,
        useMemo,
        useRef,
        Fragment,
    } = wp.element;

    const {
        Button,
        Spinner,
        Notice,
        Modal,
        TextControl,
        TextareaControl,
    } = wp.components;

    const { __, sprintf } = wp.i18n;
    const apiFetch = wp.apiFetch;

    /* ── Helpers ───────────────────────────────────────────────────── */

    /**
     * Convert a string to a URL-safe slug (approximates WordPress sanitize_title).
     *
     * Note: This is a client-side approximation. WordPress's sanitize_title also
     * converts accented/special characters (e.g., 'ø' → 'oe', 'å' → 'a'), so
     * the preview URL may differ from the actual server-side URL for inputs that
     * contain non-ASCII characters (e.g., Danish city names like "København").
     * The actual page URL is always authoritative.
     *
     * @param {string} str
     * @return {string}
     */
    const toSlug = ( str ) =>
        str
            ? str
                  .toLowerCase()
                  .replace( /\s+/g, '-' )
                  .replace( /[^a-z0-9-]/g, '' )
                  .replace( /-+/g, '-' )
                  .replace( /^-|-$/g, '' )
            : '';

    /* ── DataCenter component ──────────────────────────────────────── */
    const DataCenter = () => {
        const [ data,               setData               ] = useState( [] );
        const [ loading,            setLoading            ] = useState( true );
        const [ error,              setError              ] = useState( null );
        const [ success,            setSuccess            ] = useState( null );
        const [ editingCell,        setEditingCell        ] = useState( null );
        const [ editModal,          setEditModal          ] = useState( null );
        const [ editModalValue,     setEditModalValue     ] = useState( '' );
        const [ generatingAI,       setGeneratingAI       ] = useState( new Set() );
        const [ lookingUpCity,      setLookingUpCity      ] = useState( new Set() );
        const [ deleteConfirm,      setDeleteConfirm      ] = useState( null );
        const [ bulkProgress,       setBulkProgress       ] = useState( null );
        const [ importing,          setImporting          ] = useState( false );
        const [ rateLimitCountdown, setRateLimitCountdown ] = useState( null );
        const importFileRef = useRef( null );

        /* Countdown timer for rate-limit notice */
        useEffect( () => {
            if ( rateLimitCountdown === null || rateLimitCountdown <= 0 ) return;
            const timer = setTimeout( () => setRateLimitCountdown( prev => prev - 1 ), 1000 );
            return () => clearTimeout( timer );
        }, [ rateLimitCountdown ] );

        const formatCountdown = ( seconds ) => {
            const m = Math.floor( seconds / 60 ).toString().padStart( 2, '0' );
            const s = ( seconds % 60 ).toString().padStart( 2, '0' );
            return `${ m }:${ s }`;
        };

        /* ── Modal helpers ──────────────────────────────────────────── */
        const openEditModal = ( rowId, field, label, value, isTextarea = true, charLimit = null ) => {
            setEditModal( { rowId, field, label, isTextarea, charLimit } );
            setEditModalValue( value || '' );
        };

        const saveEditModal = async () => {
            const { rowId, field } = editModal;
            await updateCell( rowId, field, editModalValue );
            setEditModal( null );
        };

        /* ── API calls ──────────────────────────────────────────────── */
        const fetchData = async () => {
            try {
                setLoading( true );
                const response = await apiFetch( { path: '/localseo/v1/data' } );
                setData( response );
                setError( null );
            } catch ( err ) {
                setError( err.message );
            } finally {
                setLoading( false );
            }
        };

        useEffect( () => { fetchData(); }, [] );

        const updateCell = async ( rowId, columnId, value ) => {
            try {
                await apiFetch( {
                    path: `/localseo/v1/data/${ rowId }`,
                    method: 'PUT',
                    data: { [ columnId ]: value },
                } );
                setData( prevData =>
                    prevData.map( row => row.id === rowId ? { ...row, [ columnId ]: value } : row )
                );
            } catch ( err ) {
                setError( err.message );
            }
        };

        const generateAI = async ( rowId ) => {
            setGeneratingAI( prev => new Set( prev ).add( rowId ) );
            setRateLimitCountdown( null );
            try {
                const response = await apiFetch( {
                    path: `/localseo/v1/generate-ai/${ rowId }`,
                    method: 'POST',
                } );
                setData( prevData =>
                    prevData.map( row => row.id === rowId ? response : row )
                );
            } catch ( err ) {
                handleAIError( err );
            } finally {
                setGeneratingAI( prev => {
                    const next = new Set( prev );
                    next.delete( rowId );
                    return next;
                } );
            }
        };

        const handleAIError = ( err ) => {
            const retrySeconds = err.data?.retry_seconds;
            if ( err.code === 'rate_limit' || retrySeconds ) {
                setRateLimitCountdown( retrySeconds || 60 );
                setError( null );
            } else {
                setError( err.message );
            }
        };

        const lookupCity = async ( rowId, cityName ) => {
            if ( ! cityName || cityName.trim() === '' ) {
                setError( __( 'Indtast et bynavn først.', 'localseo-booster' ) );
                return;
            }
            setLookingUpCity( prev => new Set( prev ).add( rowId ) );
            try {
                const result = await apiFetch( {
                    path: `/localseo/v1/lookup-city?city=${ encodeURIComponent( cityName ) }`,
                } );
                const updates = {};
                if ( result.zip )          updates.zip          = result.zip;
                if ( result.nearby_cities ) updates.nearby_cities = result.nearby_cities;
                if ( result.city )          updates.city          = result.city;

                for ( const [ field, val ] of Object.entries( updates ) ) {
                    await updateCell( rowId, field, val );
                }
                setSuccess(
                    `Fandt postnummer ${ result.zip } for ${ result.city } og ${ result.nearby_cities.split( ',' ).length } nærliggende byer.`
                );
            } catch ( err ) {
                setError( err.message );
            } finally {
                setLookingUpCity( prev => { const n = new Set( prev ); n.delete( rowId ); return n; } );
            }
        };

        const addRow = async () => {
            try {
                const response = await apiFetch( {
                    path: '/localseo/v1/data',
                    method: 'POST',
                    data: {
                        city: '', zip: '', service_keyword: '', custom_slug: '',
                        ai_generated_intro: '', meta_title: '', meta_description: '',
                        nearby_cities: '', local_landmarks: '',
                    },
                } );
                setData( prevData => [ response, ...prevData ] );
            } catch ( err ) {
                setError( err.message );
            }
        };

        const deleteRow = ( rowId ) => setDeleteConfirm( rowId );

        const confirmDelete = async () => {
            const rowId = deleteConfirm;
            setDeleteConfirm( null );
            try {
                await apiFetch( {
                    path: `/localseo/v1/data/${ rowId }`,
                    method: 'DELETE',
                } );
                setData( prevData => prevData.filter( row => row.id !== rowId ) );
                setSuccess( __( 'Row deleted successfully.', 'localseo-booster' ) );
            } catch ( err ) {
                setError( err.message );
            }
        };

        const bulkGenerateAI = async () => {
            const rowsNeedingAI = data.filter( row =>
                ! row.ai_generated_intro || ! row.meta_title || ! row.meta_description
            );
            if ( rowsNeedingAI.length === 0 ) {
                setError( __( 'No rows need AI generation.', 'localseo-booster' ) );
                return;
            }
            setBulkProgress( { current: 0, total: rowsNeedingAI.length, success: 0, failed: 0 } );
            let successCount = 0;
            let failedCount  = 0;
            for ( let i = 0; i < rowsNeedingAI.length; i++ ) {
                const row = rowsNeedingAI[ i ];
                try {
                    const response = await apiFetch( {
                        path: `/localseo/v1/generate-ai/${ row.id }`,
                        method: 'POST',
                    } );
                    setData( prevData => prevData.map( r => r.id === row.id ? response : r ) );
                    successCount++;
                    setBulkProgress( prev => ( { ...prev, current: i + 1, success: successCount } ) );
                    if ( i < rowsNeedingAI.length - 1 ) {
                        await new Promise( resolve => setTimeout( resolve, 500 ) );
                    }
                } catch ( err ) {
                    failedCount++;
                    handleAIError( err );
                    setBulkProgress( prev => ( { ...prev, current: i + 1, failed: failedCount } ) );
                    if ( err.code === 'rate_limit' || err.data?.retry_seconds ) break;
                }
            }
            setTimeout( () => {
                setBulkProgress( null );
                setSuccess( sprintf(
                    /* translators: 1: success count, 2: failed count */
                    __( 'Generated %1$d items. Failed: %2$d', 'localseo-booster' ),
                    successCount,
                    failedCount
                ) );
            }, 1000 );
        };

        const handleExportCSV = () => {
            window.location.href = localSEOData.exportUrl;
        };

        const handleImportFile = async ( event ) => {
            const file = event.target.files[ 0 ];
            if ( ! file ) return;
            event.target.value = '';
            setImporting( true );
            setError( null );
            try {
                const text = await file.text();
                const response = await apiFetch( {
                    path: '/localseo/v1/import-csv',
                    method: 'POST',
                    data: { csv: text },
                } );
                setSuccess(
                    sprintf(
                        /* translators: 1: number of imported rows, 2: number of skipped rows */
                        __( 'Imported %1$d rows. Skipped: %2$d', 'localseo-booster' ),
                        response.imported,
                        response.skipped
                    )
                );
                fetchData();
            } catch ( err ) {
                setError( err.message );
            } finally {
                setImporting( false );
            }
        };

        /* ── Column definitions (replaces @tanstack/react-table) ───── */
        const columns = useMemo( () => [
            {
                id: 'id',
                header: 'ID',
                size: 60,
                cell: ( row ) => row.id,
            },
            {
                id: 'city',
                header: __( 'City', 'localseo-booster' ),
                size: 140,
                cell: ( row ) => {
                    const rowId  = row.id;
                    const value  = row.city;
                    const isEditing = editingCell === `${ rowId }-city`;
                    if ( isEditing ) {
                        return el( 'input', {
                            type: 'text',
                            className: 'localseo-cell-input',
                            value: value,
                            onChange: ( e ) => {
                                const newValue = e.target.value;
                                setData( prevData =>
                                    prevData.map( r => r.id === rowId ? { ...r, city: newValue } : r )
                                );
                            },
                            onBlur: () => { setEditingCell( null ); updateCell( rowId, 'city', value ); },
                            onKeyDown: ( e ) => {
                                if ( e.key === 'Enter' ) { setEditingCell( null ); updateCell( rowId, 'city', value ); }
                            },
                            autoFocus: true,
                        } );
                    }
                    return el( 'div', { className: 'lseo-city-cell' },
                        el( 'div', {
                            className: 'lseo-city-name',
                            onClick: () => setEditingCell( `${ rowId }-city` ),
                        }, value || el( 'em', { className: 'localseo-placeholder' }, __( 'Klik for at redigere', 'localseo-booster' ) ) ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            onClick: () => lookupCity( rowId, value ),
                            disabled: lookingUpCity.has( rowId ),
                            title: 'Slå postnummer og nabobyer op automatisk (DAWA)',
                            style: { flexShrink: 0, padding: '2px 5px', minHeight: 'unset' },
                        }, lookingUpCity.has( rowId ) ? el( Spinner, null ) : '🔍' )
                    );
                },
            },
            {
                id: 'zip',
                header: __( 'ZIP', 'localseo-booster' ),
                size: 80,
                cell: ( row ) => {
                    const rowId    = row.id;
                    const value    = row.zip;
                    const isEditing = editingCell === `${ rowId }-zip`;
                    if ( isEditing ) {
                        return el( 'input', {
                            type: 'text',
                            className: 'localseo-cell-input',
                            value: value,
                            onChange: ( e ) => {
                                const newValue = e.target.value;
                                setData( prevData =>
                                    prevData.map( r => r.id === rowId ? { ...r, zip: newValue } : r )
                                );
                            },
                            onBlur: () => { setEditingCell( null ); updateCell( rowId, 'zip', value ); },
                            onKeyDown: ( e ) => {
                                if ( e.key === 'Enter' ) { setEditingCell( null ); updateCell( rowId, 'zip', value ); }
                            },
                            autoFocus: true,
                        } );
                    }
                    return el( 'div', {
                        className: 'lseo-text-cell',
                        onClick: () => setEditingCell( `${ rowId }-zip` ),
                    }, value || el( 'em', { className: 'localseo-placeholder' }, __( 'Click to edit', 'localseo-booster' ) ) );
                },
            },
            {
                id: 'service_keyword',
                header: __( 'Service', 'localseo-booster' ),
                size: 150,
                cell: ( row ) => {
                    const rowId    = row.id;
                    const value    = row.service_keyword;
                    const isEditing = editingCell === `${ rowId }-service_keyword`;
                    if ( isEditing ) {
                        return el( 'input', {
                            type: 'text',
                            className: 'localseo-cell-input',
                            value: value,
                            onChange: ( e ) => {
                                const newValue = e.target.value;
                                setData( prevData =>
                                    prevData.map( r => r.id === rowId ? { ...r, service_keyword: newValue } : r )
                                );
                            },
                            onBlur: () => { setEditingCell( null ); updateCell( rowId, 'service_keyword', value ); },
                            onKeyDown: ( e ) => {
                                if ( e.key === 'Enter' ) { setEditingCell( null ); updateCell( rowId, 'service_keyword', value ); }
                            },
                            autoFocus: true,
                        } );
                    }
                    return el( 'div', {
                        className: 'lseo-text-cell',
                        onClick: () => setEditingCell( `${ rowId }-service_keyword` ),
                    }, value || el( 'em', { className: 'localseo-placeholder' }, __( 'Click to edit', 'localseo-booster' ) ) );
                },
            },
            {
                id: 'custom_slug',
                header: __( 'Page URL', 'localseo-booster' ),
                size: 150,
                cell: ( row ) => {
                    const service = toSlug( row.service_keyword );
                    const city    = toSlug( row.city );
                    const url     = service && city ? `/service/${ service }/${ city }/` : null;
                    return url
                        ? el( 'a', { href: url, target: '_blank', rel: 'noopener noreferrer' },
                            `${ service }/${ city }`
                          )
                        : el( 'em', null, __( 'Auto-generated', 'localseo-booster' ) );
                },
            },
            {
                id: 'ai_generated_intro',
                header: __( 'AI Intro', 'localseo-booster' ),
                size: 220,
                cell: ( row ) => {
                    const rowId = row.id;
                    const value = row.ai_generated_intro;
                    return el( 'div', { className: 'lseo-cell-text' },
                        el( 'div', { className: 'lseo-cell-body' },
                            value
                                ? el( 'span', { title: value },
                                    value.length > 80 ? value.substring( 0, 80 ) + '...' : value
                                  )
                                : el( 'em', { className: 'localseo-placeholder' }, __( 'Ikke genereret', 'localseo-booster' ) )
                        ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            onClick: () => openEditModal( rowId, 'ai_generated_intro', __( 'AI Introduktion', 'localseo-booster' ), value, true ),
                        }, '✏️' )
                    );
                },
            },
            {
                id: 'meta_title',
                header: __( 'Meta Titel', 'localseo-booster' ),
                size: 180,
                cell: ( row ) => {
                    const rowId = row.id;
                    const value = row.meta_title;
                    return el( 'div', { className: 'lseo-cell-text' },
                        el( 'div', { className: 'lseo-cell-body' },
                            value
                                ? el( 'span', { title: value }, value )
                                : el( 'em', { className: 'localseo-placeholder' }, __( 'Ikke sat', 'localseo-booster' ) )
                        ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            onClick: () => openEditModal( rowId, 'meta_title', __( 'Meta Titel', 'localseo-booster' ), value, false, 60 ),
                        }, '✏️' )
                    );
                },
            },
            {
                id: 'meta_description',
                header: __( 'Meta Beskrivelse', 'localseo-booster' ),
                size: 220,
                cell: ( row ) => {
                    const rowId = row.id;
                    const value = row.meta_description;
                    return el( 'div', { className: 'lseo-cell-text' },
                        el( 'div', { className: 'lseo-cell-body' },
                            value
                                ? el( 'span', { title: value },
                                    value.length > 80 ? value.substring( 0, 80 ) + '...' : value
                                  )
                                : el( 'em', { className: 'localseo-placeholder' }, __( 'Ikke sat', 'localseo-booster' ) )
                        ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            onClick: () => openEditModal( rowId, 'meta_description', __( 'Meta Beskrivelse', 'localseo-booster' ), value, true, 155 ),
                        }, '✏️' )
                    );
                },
            },
            {
                id: 'nearby_cities',
                header: __( 'Nærliggende byer', 'localseo-booster' ),
                size: 180,
                cell: ( row ) => {
                    const rowId = row.id;
                    const value = row.nearby_cities;
                    return el( 'div', { className: 'lseo-cell-text' },
                        el( 'div', { className: 'lseo-cell-body' },
                            value
                                ? el( 'span', null, value )
                                : el( 'em', { className: 'localseo-placeholder' }, __( 'Ikke sat', 'localseo-booster' ) )
                        ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            onClick: () => openEditModal( rowId, 'nearby_cities', __( 'Nærliggende byer', 'localseo-booster' ), value, false ),
                        }, '✏️' )
                    );
                },
            },
            {
                id: 'local_landmarks',
                header: __( 'Lokale seværdigheder', 'localseo-booster' ),
                size: 200,
                cell: ( row ) => {
                    const rowId = row.id;
                    const value = row.local_landmarks;
                    return el( 'div', { className: 'lseo-cell-text' },
                        el( 'div', { className: 'lseo-cell-body' },
                            value
                                ? el( 'span', { title: value },
                                    value.length > 60 ? value.substring( 0, 60 ) + '...' : value
                                  )
                                : el( 'em', { className: 'localseo-placeholder' }, __( 'Ikke sat', 'localseo-booster' ) )
                        ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            onClick: () => openEditModal( rowId, 'local_landmarks', __( 'Lokale seværdigheder', 'localseo-booster' ), value, true ),
                        }, '✏️' )
                    );
                },
            },
            {
                id: 'actions',
                header: __( 'Actions', 'localseo-booster' ),
                size: 140,
                cell: ( row ) => {
                    const rowId       = row.id;
                    const isGenerating = generatingAI.has( rowId );
                    return el( 'div', { className: 'lseo-actions-cell' },
                        el( Button, {
                            variant: 'secondary',
                            size: 'small',
                            onClick: () => generateAI( rowId ),
                            disabled: isGenerating,
                        }, isGenerating ? el( Spinner, null ) : __( 'Generer AI', 'localseo-booster' ) ),
                        el( Button, {
                            variant: 'tertiary',
                            size: 'small',
                            isDestructive: true,
                            onClick: () => deleteRow( rowId ),
                        }, __( 'Slet', 'localseo-booster' ) )
                    );
                },
            },
        ], [ editingCell, generatingAI, lookingUpCity, data ] );

        /* ── Loading state ──────────────────────────────────────────── */
        if ( loading && data.length === 0 ) {
            return el( 'div', { className: 'localseo-loading' },
                el( Spinner, null ),
                el( 'p', null, __( 'Loading data...', 'localseo-booster' ) )
            );
        }

        /* ── Render ─────────────────────────────────────────────────── */
        const progressPct = bulkProgress
            ? Math.round( ( bulkProgress.current / bulkProgress.total ) * 100 )
            : 0;

        return el( 'div', { className: 'localseo-wrap' },

            /* Title */
            el( 'h1', { style: { marginBottom: '20px' } },
                __( 'LocalSEO Datakenter', 'localseo-booster' )
            ),

            /* Error notice */
            error && el( Notice, { status: 'error', isDismissible: true, onRemove: () => setError( null ) },
                error
            ),

            /* Rate-limit countdown */
            rateLimitCountdown > 0 && el( Notice, {
                status: 'warning',
                isDismissible: true,
                onRemove: () => setRateLimitCountdown( null ),
            },
                el( Fragment, null,
                    el( 'strong', null, '⏳ AI-kvoten er midlertidigt opbrugt.' ),
                    ' Prøv igen om ',
                    el( 'strong', { style: { fontVariantNumeric: 'tabular-nums' } }, formatCountdown( rateLimitCountdown ) )
                )
            ),

            /* Success notice */
            success && el( Notice, { status: 'success', isDismissible: true, onRemove: () => setSuccess( null ) },
                success
            ),

            /* Bulk-progress notice */
            bulkProgress && el( Notice, { status: 'info', isDismissible: false },
                el( Fragment, null,
                    el( 'strong', null, __( 'Bulk AI Generation in Progress...', 'localseo-booster' ) ),
                    el( 'div', { className: 'lseo-progress-header' },
                        el( 'span', null,
                            __( 'Processing:', 'localseo-booster' ),
                            ` ${ bulkProgress.current } / ${ bulkProgress.total }`
                        ),
                        el( 'span', null, `${ progressPct }%` )
                    ),
                    el( 'div', { className: 'lseo-progress-bar-wrap' },
                        el( 'div', {
                            className: 'lseo-progress-bar',
                            style: { width: `${ progressPct }%` },
                        } )
                    ),
                    el( 'div', { className: 'lseo-progress-counts' },
                        __( 'Success:', 'localseo-booster' ), ` ${ bulkProgress.success } | `,
                        __( 'Failed:',  'localseo-booster' ), ` ${ bulkProgress.failed }`
                    )
                )
            ),

            /* Edit-field modal */
            editModal && el( Modal, {
                title: editModal.label,
                onRequestClose: () => setEditModal( null ),
                style: { width: '600px' },
            },
                editModal.isTextarea
                    ? el( TextareaControl, {
                        label: editModal.label,
                        hideLabelFromVision: true,
                        value: editModalValue,
                        onChange: setEditModalValue,
                        rows: 8,
                    } )
                    : el( TextControl, {
                        label: editModal.label,
                        hideLabelFromVision: true,
                        value: editModalValue,
                        onChange: setEditModalValue,
                    } ),
                editModal.charLimit && el( 'p', {
                    className: 'lseo-char-count' +
                        ( editModalValue.length > editModal.charLimit ? ' over-limit' : '' ),
                }, `${ editModalValue.length } / ${ editModal.charLimit } tegn` ),
                el( 'div', { className: 'lseo-modal-footer' },
                    el( Button, { variant: 'primary',    onClick: saveEditModal },          __( 'Gem',      'localseo-booster' ) ),
                    el( Button, { variant: 'secondary',  onClick: () => setEditModal( null ) }, __( 'Annuller', 'localseo-booster' ) )
                )
            ),

            /* Delete-confirm modal */
            deleteConfirm && el( Modal, {
                title: __( 'Bekræft sletning', 'localseo-booster' ),
                onRequestClose: () => setDeleteConfirm( null ),
            },
                el( 'p', null, __( 'Er du sikker på, at du vil slette denne række?', 'localseo-booster' ) ),
                el( 'div', { className: 'lseo-modal-footer' },
                    el( Button, { variant: 'primary', isDestructive: true, onClick: confirmDelete },       __( 'Slet',      'localseo-booster' ) ),
                    el( Button, { variant: 'secondary',                    onClick: () => setDeleteConfirm( null ) }, __( 'Annuller', 'localseo-booster' ) )
                )
            ),

            /* Toolbar */
            el( 'div', { className: 'localseo-toolbar' },
                el( Button, {
                    variant: 'primary',
                    onClick: addRow,
                    disabled: bulkProgress !== null,
                }, __( 'Tilføj ny række', 'localseo-booster' ) ),
                el( Button, {
                    variant: 'secondary',
                    onClick: bulkGenerateAI,
                    disabled: bulkProgress !== null,
                }, bulkProgress
                    ? __( 'Genererer...', 'localseo-booster' )
                    : __( 'Generer alle manglende AI-felter', 'localseo-booster' )
                ),
                el( Button, {
                    variant: 'secondary',
                    onClick: handleExportCSV,
                    disabled: bulkProgress !== null || importing,
                }, __( 'Eksporter CSV', 'localseo-booster' ) ),
                el( Button, {
                    variant: 'secondary',
                    onClick: () => importFileRef.current && importFileRef.current.click(),
                    disabled: bulkProgress !== null || importing,
                }, importing
                    ? __( 'Importerer...', 'localseo-booster' )
                    : __( 'Importer CSV',  'localseo-booster' )
                ),
                /* Hidden file input for CSV import */
                el( 'input', {
                    type: 'file',
                    accept: '.csv,text/csv',
                    ref: importFileRef,
                    style: { display: 'none' },
                    onChange: handleImportFile,
                } ),
                el( Button, {
                    variant: 'tertiary',
                    onClick: fetchData,
                    disabled: bulkProgress !== null,
                }, __( 'Opdater', 'localseo-booster' ) )
            ),

            /* Data table */
            el( 'div', { className: 'localseo-table-wrap' },
                el( 'table', { className: 'localseo-table' },
                    el( 'thead', null,
                        el( 'tr', null,
                            ...columns.map( col =>
                                el( 'th', { key: col.id, style: { width: col.size } }, col.header )
                            )
                        )
                    ),
                    el( 'tbody', null,
                        ...data.map( row =>
                            el( 'tr', { key: row.id },
                                ...columns.map( col =>
                                    el( 'td', { key: col.id, style: { width: col.size } }, col.cell( row ) )
                                )
                            )
                        )
                    )
                )
            ),

            /* Empty state */
            data.length === 0 && el( 'div', { className: 'localseo-empty' },
                el( 'p', null, __( 'Ingen data endnu. Klik på "Tilføj ny række" for at komme i gang.', 'localseo-booster' ) )
            )
        );
    };

    /* ── Mount ─────────────────────────────────────────────────────── */
    const rootElement = document.getElementById( 'localseo-data-center' );
    if ( rootElement ) {
        if ( wp.element.createRoot ) {
            /* React 18 / WordPress 6.2+ */
            wp.element.createRoot( rootElement ).render( el( DataCenter, null ) );
        } else {
            /* WordPress < 6.2 */
            wp.element.render( el( DataCenter, null ), rootElement );
        }
    }
} )();
