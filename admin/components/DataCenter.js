import { useState, useEffect, useMemo } from '@wordpress/element';
import { Button, Spinner, Notice, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
    useReactTable,
    getCoreRowModel,
    flexRender,
    createColumnHelper,
} from '@tanstack/react-table';

const columnHelper = createColumnHelper();

const DataCenter = () => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);
    const [editingCell, setEditingCell] = useState(null);
    const [generatingAI, setGeneratingAI] = useState(new Set());
    const [deleteConfirm, setDeleteConfirm] = useState(null);
    const [bulkProgress, setBulkProgress] = useState(null);

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
            setError(err.message);
        } finally {
            setGeneratingAI(prev => {
                const next = new Set(prev);
                next.delete(rowId);
                return next;
            });
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
                setBulkProgress(prev => ({
                    ...prev,
                    current: i + 1,
                    failed: failedCount
                }));
            }
        }
        
        setTimeout(() => {
            setBulkProgress(null);
            setSuccess(__(`Generated ${successCount} items. Failed: ${failedCount}`, 'localseo-booster'));
        }, 1000);
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
                    <div onClick={() => setEditingCell(`${rowId}-city`)}>
                        {value || <em>{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 120,
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
                    <div onClick={() => setEditingCell(`${rowId}-zip`)}>
                        {value || <em>{__('Click to edit', 'localseo-booster')}</em>}
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
                    <div onClick={() => setEditingCell(`${rowId}-service_keyword`)}>
                        {value || <em>{__('Click to edit', 'localseo-booster')}</em>}
                    </div>
                );
            },
            size: 150,
        }),
        columnHelper.accessor('custom_slug', {
            header: __('Slug', 'localseo-booster'),
            cell: info => {
                const value = info.getValue();
                return value ? (
                    <a href={`/localseo/${value}`} target="_blank" rel="noopener noreferrer">
                        {value}
                    </a>
                ) : (
                    <em>{__('Auto-generated', 'localseo-booster')}</em>
                );
            },
            size: 150,
        }),
        columnHelper.accessor('ai_generated_intro', {
            header: __('AI Intro', 'localseo-booster'),
            cell: info => {
                const value = info.getValue();
                return value ? (
                    <div className="truncate" title={value}>
                        {value.substring(0, 50)}...
                    </div>
                ) : (
                    <em>{__('Not generated', 'localseo-booster')}</em>
                );
            },
            size: 200,
        }),
        columnHelper.accessor('meta_title', {
            header: __('Meta Title', 'localseo-booster'),
            cell: info => {
                const value = info.getValue();
                return value ? (
                    <div className="truncate" title={value}>
                        {value}
                    </div>
                ) : (
                    <em>{__('Not set', 'localseo-booster')}</em>
                );
            },
            size: 150,
        }),
        columnHelper.display({
            id: 'actions',
            header: __('Actions', 'localseo-booster'),
            cell: info => {
                const rowId = info.row.original.id;
                const isGenerating = generatingAI.has(rowId);

                return (
                    <div className="action-buttons">
                        <Button
                            variant="secondary"
                            size="small"
                            onClick={() => generateAI(rowId)}
                            disabled={isGenerating}
                        >
                            {isGenerating ? <Spinner /> : __('Generate AI', 'localseo-booster')}
                        </Button>
                        <Button
                            variant="tertiary"
                            size="small"
                            isDestructive
                            onClick={() => deleteRow(rowId)}
                        >
                            {__('Delete', 'localseo-booster')}
                        </Button>
                    </div>
                );
            },
            size: 180,
        }),
    ], [editingCell, generatingAI, data]);

    const table = useReactTable({
        data,
        columns,
        getCoreRowModel: getCoreRowModel(),
    });

    if (loading && data.length === 0) {
        return (
            <div className="localseo-loading">
                <Spinner />
                <p>{__('Loading data...', 'localseo-booster')}</p>
            </div>
        );
    }

    return (
        <div className="localseo-data-center">
            <h1>{__('LocalSEO Data Center', 'localseo-booster')}</h1>
            
            {error && (
                <Notice status="error" isDismissible onRemove={() => setError(null)}>
                    {error}
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
                    <div style={{ marginTop: '10px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '5px' }}>
                            <span>{__('Processing:', 'localseo-booster')} {bulkProgress.current} / {bulkProgress.total}</span>
                            <span>{Math.round((bulkProgress.current / bulkProgress.total) * 100)}%</span>
                        </div>
                        <div style={{ 
                            width: '100%', 
                            height: '20px', 
                            backgroundColor: '#f0f0f0', 
                            borderRadius: '4px',
                            overflow: 'hidden'
                        }}>
                            <div style={{ 
                                width: `${(bulkProgress.current / bulkProgress.total) * 100}%`, 
                                height: '100%', 
                                backgroundColor: '#2271b1',
                                transition: 'width 0.3s ease'
                            }} />
                        </div>
                        <div style={{ marginTop: '5px', fontSize: '12px', color: '#666' }}>
                            {__('Success:', 'localseo-booster')} {bulkProgress.success} | {__('Failed:', 'localseo-booster')} {bulkProgress.failed}
                        </div>
                    </div>
                </Notice>
            )}

            {deleteConfirm && (
                <Modal
                    title={__('Confirm Delete', 'localseo-booster')}
                    onRequestClose={() => setDeleteConfirm(null)}
                >
                    <p>{__('Are you sure you want to delete this row?', 'localseo-booster')}</p>
                    <div style={{ display: 'flex', gap: '10px', marginTop: '20px' }}>
                        <Button variant="primary" isDestructive onClick={confirmDelete}>
                            {__('Delete', 'localseo-booster')}
                        </Button>
                        <Button variant="secondary" onClick={() => setDeleteConfirm(null)}>
                            {__('Cancel', 'localseo-booster')}
                        </Button>
                    </div>
                </Modal>
            )}

            <div className="toolbar">
                <Button variant="primary" onClick={addRow} disabled={bulkProgress !== null}>
                    {__('Add New Row', 'localseo-booster')}
                </Button>
                <Button variant="secondary" onClick={bulkGenerateAI} disabled={bulkProgress !== null}>
                    {bulkProgress ? __('Generating...', 'localseo-booster') : __('Generate All Missing AI Fields', 'localseo-booster')}
                </Button>
                <Button variant="tertiary" onClick={fetchData} disabled={bulkProgress !== null}>
                    {__('Refresh', 'localseo-booster')}
                </Button>
            </div>

            <div className="table-container">
                <table className="localseo-table">
                    <thead>
                        {table.getHeaderGroups().map(headerGroup => (
                            <tr key={headerGroup.id}>
                                {headerGroup.headers.map(header => (
                                    <th
                                        key={header.id}
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
                            <tr key={row.id}>
                                {row.getVisibleCells().map(cell => (
                                    <td
                                        key={cell.id}
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
                <div className="empty-state">
                    <p>{__('No data yet. Click "Add New Row" to get started.', 'localseo-booster')}</p>
                </div>
            )}
        </div>
    );
};

export default DataCenter;
