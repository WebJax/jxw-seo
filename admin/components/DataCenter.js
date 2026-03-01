import { useState, useEffect, useMemo, useRef } from '@wordpress/element';
import { Button, Spinner, Notice, Modal, TextControl, TextareaControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    createColumnHelper,
} from '@tanstack/react-table';

const columnHelper = createColumnHelper();

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

const DataCenter = () => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [editingCell, setEditingCell] = useState(null);
    const [editModal, setEditModal] = useState(null); // { rowId, field, label, value, isTextarea, charLimit }
    const [editModalValue, setEditModalValue] = useState('');
    const [generatingAI, setGeneratingAI] = useState(new Set());
    const [lookingUpCity, setLookingUpCity] = useState(new Set());
    const [deleteConfirm, setDeleteConfirm] = useState(null);
    const [bulkProgress, setBulkProgress] = useState(null);
    const [importing, setImporting] = useState(false);
    const [rateLimitCountdown, setRateLimitCountdown] = useState(null); // seconds remaining
    const importFileRef = useRef(null);

    // Countdown timer for rate limit
    useEffect(() => {
        if (rateLimitCountdown === null || rateLimitCountdown <= 0) return;
        const timer = setTimeout(() => setRateLimitCountdown(prev => prev - 1), 1000);
        return () => clearTimeout(timer);
    }, [rateLimitCountdown]);

    const formatCountdown = (seconds) => {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = (seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    const openEditModal = (rowId, field, label, value, isTextarea = true, charLimit = null) => {
        setEditModal({ rowId, field, label, isTextarea, charLimit });
        setEditModalValue(value || '');
    };

    const saveEditModal = async () => {
        const { rowId, field } = editModal;
        await updateCell(rowId, field, editModalValue);
        setEditModal(null);
    };

    // Fetch data from API
    const fetchData = async () => {
        try {
            setLoading(true);
            const response = await apiFetch({
                path: '/localseo/v1/data',
            });
            setData(response);
            setError(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        fetchData();
    }, []);

    // Update cell value
    const updateCell = async (rowId, columnId, value) => {
        try {
            await apiFetch({
                path: `/localseo/v1/data/${rowId}`,
                method: 'PUT',
                data: { [columnId]: value },
            });
            
            // Update local state
            setData(prevData =>
                prevData.map(row =>
                    row.id === rowId ? { ...row, [columnId]: value } : row
                )
            );
        } catch (err) {
            setError(err.message);
        }
    };

    // Generate AI content for a row
    const generateAI = async (rowId) => {
        setGeneratingAI(prev => new Set(prev).add(rowId));
        setRateLimitCountdown(null);
        try {
            const response = await apiFetch({
                path: `/localseo/v1/generate-ai/${rowId}`,
                method: 'POST',
            });
            
            // Update local state with AI-generated content
            setData(prevData =>
                prevData.map(row =>
                    row.id === rowId ? response : row
                )
            );
        } catch (err) {
            handleAIError(err);
        } finally {
            setGeneratingAI(prev => {
                const next = new Set(prev);
                next.delete(rowId);
                return next;
            });
        }
    };

    // Handle rate-limit errors uniformly
    const handleAIError = (err) => {
        const retrySeconds = err.data?.retry_seconds;
        if (err.code === 'rate_limit' || retrySeconds) {
            setRateLimitCountdown(retrySeconds || 60);
            setError(null);
        } else {
            setError(err.message);
        }
    };

    // Lookup city data via DAWA (zip + nearby cities)
    const lookupCity = async (rowId, cityName) => {
        if (!cityName || cityName.trim() === '') {
            setError('Indtast et bynavn først.');
            return;
        }
        setLookingUpCity(prev => new Set(prev).add(rowId));
        try {
            const result = await apiFetch({
                path: `/localseo/v1/lookup-city?city=${encodeURIComponent(cityName)}`,
            });
            // Auto-fill zip and nearby_cities (and correct city capitalisation)
            const updates = {};
            if (result.zip) updates.zip = result.zip;
            if (result.nearby_cities) updates.nearby_cities = result.nearby_cities;
            if (result.city) updates.city = result.city;

            // Save each changed field to the DB
            for (const [field, val] of Object.entries(updates)) {
                await updateCell(rowId, field, val);
            }
            setSuccess(`Fandt postnummer ${result.zip} for ${result.city} og ${result.nearby_cities.split(',').length} nærliggende byer.`);
        } catch (err) {
            setError(err.message);
        } finally {
            setLookingUpCity(prev => { const n = new Set(prev); n.delete(rowId); return n; });
        }
    };

    // Add new row
    const addRow = async () => {
        try {
            const response = await apiFetch({
                path: '/localseo/v1/data',
                method: 'POST',
                data: {
                    city: '',
                    zip: '',
                    service_keyword: '',
                    custom_slug: '',
                    ai_generated_intro: '',
                    meta_title: '',
                    meta_description: '',
                    nearby_cities: '',
                    local_landmarks: '',
                },
            });
            
            setData(prevData => [response, ...prevData]);
        } catch (err) {
            setError(err.message);
        }
    };

    // Delete row
    const deleteRow = async (rowId) => {
        setDeleteConfirm(rowId);
    };

    const confirmDelete = async () => {
        const rowId = deleteConfirm;
        setDeleteConfirm(null);

        try {
            await apiFetch({
                path: `/localseo/v1/data/${rowId}`,
                method: 'DELETE',
            });
            
            setData(prevData => prevData.filter(row => row.id !== rowId));
            setSuccess(__('Row deleted successfully.', 'localseo-booster'));
        } catch (err) {
            setError(err.message);
        }
    };

    // Bulk generate AI
    const bulkGenerateAI = async () => {
        const rowsNeedingAI = data.filter(row => 
            !row.ai_generated_intro || !row.meta_title || !row.meta_description
        );
        
        if (rowsNeedingAI.length === 0) {
            setError(__('No rows need AI generation.', 'localseo-booster'));
            return;
        }
        
        setBulkProgress({ current: 0, total: rowsNeedingAI.length, success: 0, failed: 0 });
        
        let successCount = 0;
        let failedCount = 0;
        
        for (let i = 0; i < rowsNeedingAI.length; i++) {
            const row = rowsNeedingAI[i];
            
            try {
                const response = await apiFetch({
                    path: `/localseo/v1/generate-ai/${row.id}`,
                    method: 'POST',
                });
                
                // Update local state with AI-generated content
                setData(prevData =>
                    prevData.map(r =>
                        r.id === row.id ? response : r
                    )
                );
                
                successCount++;
                setBulkProgress(prev => ({
                    ...prev,
                    current: i + 1,
                    success: successCount
                }));
                
                // Add delay to avoid rate limiting
                if (i < rowsNeedingAI.length - 1) {
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            } catch (err) {
                failedCount++;
                handleAIError(err);
                setBulkProgress(prev => ({
                    ...prev,
                    current: i + 1,
                    failed: failedCount
                }));
                // If rate-limited, abort bulk
                if (err.code === 'rate_limit' || err.data?.retry_seconds) break;
            }
        }
        
        setTimeout(() => {
            setBulkProgress(null);
            setSuccess(__(`Generated ${successCount} items. Failed: ${failedCount}`, 'localseo-booster'));
        }, 1000);
    };

    // Export CSV – opens the admin-post download URL
    const handleExportCSV = () => {
        window.location.href = localSEOData.exportUrl;
    };

    // Import CSV – read the file client-side, POST content to REST API
    const handleImportFile = async ( event ) => {
        const file = event.target.files[ 0 ];
        if ( ! file ) return;

        // Reset the input so the same file can be selected again later
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

    // Define columns
    const columns = useMemo(() => [
        columnHelper.accessor('id', {
            header: 'ID',
            cell: info => info.getValue(),
            size: 60,
        }),
        columnHelper.accessor('city', {
            header: __('City', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                const isEditing = editingCell === `${rowId}-city`;

                return isEditing ? (
                    <input
                        type="text"
                        className="w-full px-2 py-1 border border-[#8c8f94] rounded-sm text-sm"
                        value={value}
                        onChange={e => {
                            const newValue = e.target.value;
                            setData(prevData =>
                                prevData.map(row =>
                                    row.id === rowId ? { ...row, city: newValue } : row
                                )
                            );
                        }}
                        onBlur={() => {
                            setEditingCell(null);
                            updateCell(rowId, 'city', value);
                        }}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                setEditingCell(null);
                                updateCell(rowId, 'city', value);
                            }
                        }}
                        autoFocus
                    />
                ) : (
                    <div className="flex items-center gap-1">
                        <div onClick={() => setEditingCell(`${rowId}-city`)} className="flex-1 cursor-pointer">
                            {value || <em>{__('Klik for at redigere', 'localseo-booster')}</em>}
                        </div>
                        <Button
                            variant="tertiary"
                            size="small"
                            onClick={() => lookupCity(rowId, value)}
                            disabled={lookingUpCity.has(rowId)}
                            title="Slå postnummer og nabobyer op automatisk (DAWA)"
                            style={{ flexShrink: 0, padding: '2px 5px', minHeight: 'unset' }}
                        >
                            {lookingUpCity.has(rowId) ? <Spinner /> : '🔍'}
                        </Button>
                    </div>
                );
            },
            size: 140,
        }),
        columnHelper.accessor('zip', {
            header: __('ZIP', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                const isEditing = editingCell === `${rowId}-zip`;

                return isEditing ? (
                    <input
                        type="text"
                        className="w-full px-2 py-1 border border-[#8c8f94] rounded-sm text-sm"
                        value={value}
                        onChange={e => {
                            const newValue = e.target.value;
                            setData(prevData =>
                                prevData.map(row =>
                                    row.id === rowId ? { ...row, zip: newValue } : row
                                )
                            );
                        }}
                        onBlur={() => {
                            setEditingCell(null);
                            updateCell(rowId, 'zip', value);
                        }}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                setEditingCell(null);
                                updateCell(rowId, 'zip', value);
                            }
                        }}
                        autoFocus
                    />
                ) : (
                    <div className="cursor-pointer min-h-[20px] break-words leading-normal" onClick={() => setEditingCell(`${rowId}-zip`)}>
                        {value || <em className="text-[#8c8f94]">{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 80,
        }),
        columnHelper.accessor('service_keyword', {
            header: __('Service', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                const isEditing = editingCell === `${rowId}-service_keyword`;

                return isEditing ? (
                    <input
                        type="text"
                        className="w-full px-2 py-1 border border-[#8c8f94] rounded-sm text-sm"
                        value={value}
                        onChange={e => {
                            const newValue = e.target.value;
                            setData(prevData =>
                                prevData.map(row =>
                                    row.id === rowId ? { ...row, service_keyword: newValue } : row
                                )
                            );
                        }}
                        onBlur={() => {
                            setEditingCell(null);
                            updateCell(rowId, 'service_keyword', value);
                        }}
                        onKeyDown={e => {
                            if (e.key === 'Enter') {
                                setEditingCell(null);
                                updateCell(rowId, 'service_keyword', value);
                            }
                        }}
                        autoFocus
                    />
                ) : (
                    <div className="cursor-pointer min-h-[20px] break-words leading-normal" onClick={() => setEditingCell(`${rowId}-service_keyword`)}>
                        {value || <em className="text-[#8c8f94]">{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 150,
        }),
        columnHelper.accessor('custom_slug', {
            header: __('Page URL', 'localseo-booster'),
            cell: info => {
                const row = info.row.original;
                const service = toSlug( row.service_keyword );
                const city    = toSlug( row.city );
                const url     = service && city ? `/service/${ service }/${ city }/` : null;
                return url ? (
                    <a href={ url } target="_blank" rel="noopener noreferrer">
                        { `${ service }/${ city }` }
                    </a>
                ) : (
                    <em>{ __( 'Auto-generated', 'localseo-booster' ) }</em>
                );
            },
            size: 150,
        }),
        columnHelper.accessor('ai_generated_intro', {
            header: __('AI Intro', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                return (
                    <div className="lseo-cell-text flex items-start gap-1.5">
                        <div className="flex-1 min-w-0 text-[13px] leading-normal overflow-hidden break-words">
                            {value
                                ? <span title={value}>{value.length > 80 ? value.substring(0, 80) + '...' : value}</span>
                                : <em className="text-[#8c8f94]">{__('Ikke genereret', 'localseo-booster')}</em>
                            }
                        </div>
                        <Button
                            variant="tertiary"
                            size="small"
                            onClick={() => openEditModal(rowId, 'ai_generated_intro', __('AI Introduktion', 'localseo-booster'), value, true)}
                        >
                            ✏️
                        </Button>
                    </div>
                );
            },
            size: 220,
        }),
        columnHelper.accessor('meta_title', {
            header: __('Meta Titel', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                return (
                    <div className="lseo-cell-text flex items-start gap-1.5">
                        <div className="flex-1 min-w-0 text-[13px] leading-normal overflow-hidden break-words">
                            {value
                                ? <span title={value}>{value}</span>
                                : <em className="text-[#8c8f94]">{__('Ikke sat', 'localseo-booster')}</em>
                            }
                        </div>
                        <Button
                            variant="tertiary"
                            size="small"
                            onClick={() => openEditModal(rowId, 'meta_title', __('Meta Titel', 'localseo-booster'), value, false, 60)}
                        >
                            ✏️
                        </Button>
                    </div>
                );
            },
            size: 180,
        }),
        columnHelper.accessor('meta_description', {
            header: __('Meta Beskrivelse', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                return (
                    <div className="lseo-cell-text flex items-start gap-1.5">
                        <div className="flex-1 min-w-0 text-[13px] leading-normal overflow-hidden break-words">
                            {value
                                ? <span title={value}>{value.length > 80 ? value.substring(0, 80) + '...' : value}</span>
                                : <em className="text-[#8c8f94]">{__('Ikke sat', 'localseo-booster')}</em>
                            }
                        </div>
                        <Button
                            variant="tertiary"
                            size="small"
                            onClick={() => openEditModal(rowId, 'meta_description', __('Meta Beskrivelse', 'localseo-booster'), value, true, 155)}
                        >
                            ✏️
                        </Button>
                    </div>
                );
            },
            size: 220,
        }),
        columnHelper.accessor('nearby_cities', {
            header: __('Nærliggende byer', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                return (
                    <div className="lseo-cell-text flex items-start gap-1.5">
                        <div className="flex-1 min-w-0 text-[13px] leading-normal overflow-hidden break-words">
                            {value
                                ? <span>{value}</span>
                                : <em className="text-[#8c8f94]">{__('Ikke sat', 'localseo-booster')}</em>
                            }
                        </div>
                        <Button
                            variant="tertiary"
                            size="small"
                            onClick={() => openEditModal(rowId, 'nearby_cities', __('Nærliggende byer', 'localseo-booster'), value, false)}
                        >
                            ✏️
                        </Button>
                    </div>
                );
            },
            size: 180,
        }),
        columnHelper.accessor('local_landmarks', {
            header: __('Lokale seværdigheder', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const value = info.getValue();
                return (
                    <div className="lseo-cell-text flex items-start gap-1.5">
                        <div className="flex-1 min-w-0 text-[13px] leading-normal overflow-hidden break-words">
                            {value
                                ? <span title={value}>{value.length > 60 ? value.substring(0, 60) + '...' : value}</span>
                                : <em className="text-[#8c8f94]">{__('Ikke sat', 'localseo-booster')}</em>
                            }
                        </div>
                        <Button
                            variant="tertiary"
                            size="small"
                            onClick={() => openEditModal(rowId, 'local_landmarks', __('Lokale seværdigheder', 'localseo-booster'), value, true)}
                        >
                            ✏️
                        </Button>
                    </div>
                );
            },
            size: 200,
        }),
        columnHelper.display({
            id: 'actions',
            header: __('Actions', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const isGenerating = generatingAI.has(rowId);

                return (
                    <div className="flex gap-1.5 flex-wrap">
                        <Button
                            variant="secondary"
                            size="small"
                            onClick={() => generateAI(rowId)}
                            disabled={isGenerating}
                        >
                            {isGenerating ? <Spinner /> : __('Generer AI', 'localseo-booster')}
                        </Button>
                        <Button
                            variant="tertiary"
                            size="small"
                            isDestructive
                            onClick={() => deleteRow(rowId)}
                        >
                            {__('Slet', 'localseo-booster')}
                        </Button>
                    </div>
                );
            },
            size: 180,
        }),
    ], [editingCell, generatingAI, lookingUpCity, data]);

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    if (loading && data.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center min-h-[300px]">
                <Spinner />
                <p>{__('Loading data...', 'localseo-booster')}</p>
            </div>
        );
    }

    return (
        <div className="my-5">
            <h1 className="mb-5">{__('LocalSEO Datakenter', 'localseo-booster')}</h1>
            
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
                </Notice>
            )}

            {rateLimitCountdown > 0 && (
                <Notice status="warning" isDismissible onRemove={() => setRateLimitCountdown(null)}>
                    <strong>⏳ AI-kvoten er midlertidigt opbrugt.</strong>
                    {' '}Prøv igen om <strong style={{ fontVariantNumeric: 'tabular-nums' }}>{formatCountdown(rateLimitCountdown)}</strong>
                </Notice>
            )}

            {success && (
                <Notice status="success" isDismissible onRemove={() => setSuccess(null)}>
                    {success}
                </Notice>
            )}

            {bulkProgress && (
                <Notice status="info" isDismissible={false}>
                    <strong>{__('Bulk AI Generation in Progress...', 'localseo-booster')}</strong>
                    <div className="mt-2.5">
                        <div className="flex justify-between mb-1.5">
                            <span>{__('Processing:', 'localseo-booster')} {bulkProgress.current} / {bulkProgress.total}</span>
                            <span>{Math.round((bulkProgress.current / bulkProgress.total) * 100)}%</span>
                        </div>
                        <div className="w-full h-5 bg-[#f0f0f0] rounded overflow-hidden">
                            <div
                                className="h-full bg-[#2271b1] transition-[width] duration-300 ease-in-out"
                                style={{ width: `${(bulkProgress.current / bulkProgress.total) * 100}%` }}
                            />
                        </div>
                        <div className="mt-1.5 text-xs text-[#666]">
                            {__('Success:', 'localseo-booster')} {bulkProgress.success} | {__('Failed:', 'localseo-booster')} {bulkProgress.failed}
                        </div>
                    </div>
                </Notice>
            )}

            {editModal && (
                <Modal
                    title={editModal.label}
                    onRequestClose={() => setEditModal(null)}
                    style={{ width: '600px' }}
                >
                    {editModal.isTextarea ? (
                        <TextareaControl
                            label={editModal.label}
                            hideLabelFromVision
                            value={editModalValue}
                            onChange={setEditModalValue}
                            rows={8}
                            className="w-full text-sm"
                        />
                    ) : (
                        <TextControl
                            label={editModal.label}
                            hideLabelFromVision
                            value={editModalValue}
                            onChange={setEditModalValue}
                            className="w-full text-sm"
                        />
                    )}
                    {editModal.charLimit && (
                        <p className={`text-xs mt-1 ${editModalValue.length > editModal.charLimit ? 'text-[#cc1818]' : 'text-[#8c8f94]'}`}>
                            {editModalValue.length} / {editModal.charLimit} tegn
                        </p>
                    )}
                    <div className="flex gap-2.5 mt-4">
                        <Button variant="primary" onClick={saveEditModal}>
                            {__('Gem', 'localseo-booster')}
                        </Button>
                        <Button variant="secondary" onClick={() => setEditModal(null)}>
                            {__('Annuller', 'localseo-booster')}
                        </Button>
                    </div>
                </Modal>
            )}

            {deleteConfirm && (
                <Modal
                    title={__('Bekræft sletning', 'localseo-booster')}
                    onRequestClose={() => setDeleteConfirm(null)}
                >
                    <p>{__('Er du sikker på, at du vil slette denne række?', 'localseo-booster')}</p>
                    <div className="flex gap-2.5 mt-5">
                        <Button variant="primary" isDestructive onClick={confirmDelete}>
                            {__('Slet', 'localseo-booster')}
                        </Button>
                        <Button variant="secondary" onClick={() => setDeleteConfirm(null)}>
                            {__('Annuller', 'localseo-booster')}
                        </Button>
                    </div>
                </Modal>
            )}

            <div className="flex gap-2.5 mb-5 flex-wrap">
                <Button variant="primary" onClick={addRow} disabled={bulkProgress !== null}>
                    {__('Tilføj ny række', 'localseo-booster')}
                </Button>
                <Button variant="secondary" onClick={bulkGenerateAI} disabled={bulkProgress !== null}>
                    {bulkProgress ? __('Genererer...', 'localseo-booster') : __('Generer alle manglende AI-felter', 'localseo-booster')}
                </Button>
                <Button variant="secondary" onClick={handleExportCSV} disabled={bulkProgress !== null || importing}>
                    {__('Eksporter CSV', 'localseo-booster')}
                </Button>
                <Button variant="secondary" onClick={() => importFileRef.current && importFileRef.current.click()} disabled={bulkProgress !== null || importing}>
                    {importing ? __('Importerer...', 'localseo-booster') : __('Importer CSV', 'localseo-booster')}
                </Button>
                <input
                    type="file"
                    accept=".csv,text/csv"
                    ref={importFileRef}
                    className="hidden"
                    onChange={handleImportFile}
                />
                <Button variant="tertiary" onClick={fetchData} disabled={bulkProgress !== null}>
                    {__('Opdater', 'localseo-booster')}
                </Button>
            </div>

            <div className="overflow-x-auto bg-white border border-[#ccc] rounded">
                <table className="localseo-table w-full border-collapse table-auto">
                    <thead className="bg-[#f0f0f1] sticky top-0 z-10">
                        {table.getHeaderGroups().map(headerGroup => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map(header => (
                                    <th
                                        key={header.id}
                                        className="px-2 py-3 text-left font-semibold border-b-2 border-[#ccc]"
                                        style={{ width: header.getSize() }}
                                    >
                                        {flexRender(
                                            header.column.columnDef.header,
                                            header.getContext()
                                        )}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.map(row => (
                            <tr key={row.id} className="hover:bg-[#f9f9f9]">
                                {row.getVisibleCells().map(cell => (
                                    <td
                                        key={cell.id}
                                        className="p-2 border-b border-[#e0e0e0] align-top break-words"
                                        style={{ width: cell.column.getSize() }}
                                    >
                                        {flexRender(
                                            cell.column.columnDef.cell,
                                            cell.getContext()
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {data.length === 0 && (
                <div className="text-center p-10 text-[#8c8f94]">
                    <p className="text-base">{__('Ingen data endnu. Klik på "Tilføj ny række" for at komme i gang.', 'localseo-booster')}</p>
                </div>
            )}
        </div>
    );
};

export default DataCenter;
